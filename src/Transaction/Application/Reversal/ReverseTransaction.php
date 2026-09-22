<?php

declare(strict_types=1);

namespace Transaction\Application\Reversal;

use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Posting\Posting;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\Exception\DuplicateReversalReference;
use Transaction\Domain\Reversal\Exception\InsufficientReversalFunds;
use Transaction\Domain\Reversal\Exception\ReversibleEntryNotFound;
use Transaction\Domain\Reversal\Exception\TransactionAlreadyReversed;
use Transaction\Domain\Reversal\Repository\ReversalRepository;
use Transaction\Domain\Reversal\Reversal;
use Transaction\Domain\Reversal\ValueObject\ReversalReason;

/** Cancels a posted transaction by recording equal and opposite postings. */
final readonly class ReverseTransaction
{
    public function __construct(
        private LedgerEntryRepository $entries,
        private ReversalRepository $reversals,
        private BalanceProjectionRepository $balances,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ReverseTransactionCommand $command): Reversal
    {
        $originalEntry = $this->entries->findById($command->originalLedgerEntryId);

        if ($originalEntry === null || $originalEntry->status() !== EntryStatus::Posted) {
            throw ReversibleEntryNotFound::create();
        }

        if ($this->reversals->originalEntryWasReversed($originalEntry->id())) {
            throw TransactionAlreadyReversed::create();
        }

        $reference = new TransactionReference($command->reference);

        if ($this->reversals->referenceExists($reference)) {
            throw DuplicateReversalReference::create();
        }

        $reason = new ReversalReason($command->reason);

        [$reversal, $domainEvents] = $this->transactions->run(function () use (
            $command,
            $originalEntry,
            $reference,
            $reason
        ): array {

            $this->lockAndCheckDebitedLedgers($originalEntry);
            $now = $this->clock->now();

            $reversalEntry = LedgerEntry::draft(
                new LedgerEntryId($this->uuidGenerator->generate()->value()),
                $originalEntry->ledgerId(),
                new EntryReference($reference->value()),
                new EntryDescription('Reversal ' . $reference->value()),
                $command->occurredAt,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId,
            );

            foreach ($originalEntry->postings() as $posting) {
                $oppositeSide = $posting->side() === PostingSide::Debit ? PostingSide::Credit : PostingSide::Debit;
                $reversalEntry->addPosting(
                    new PostingId($this->uuidGenerator->generate()->value()),
                    $posting->ledgerId(),
                    $oppositeSide,
                    $posting->amount(),
                    $now,
                    $this->uuidGenerator->generate(),
                    $command->correlationId
                );
            }

            $reversalEntry->post($now, $this->uuidGenerator->generate(), $command->correlationId);

            $reversal = Reversal::complete(
                new TransactionId($this->uuidGenerator->generate()->value()),
                $originalEntry->id(),
                $reversalEntry->id(),
                $reference,
                $reason,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $this->entries->save($reversalEntry);
            $this->reversals->save($reversal);
            // Applying inside this transaction keeps every affected balance consistent.
            $this->balances->apply($reversalEntry, $now);

            return [$reversal, [...$reversalEntry->pullDomainEvents(), ...$reversal->pullDomainEvents()]];
        });

        $this->events->publish($domainEvents);

        return $reversal;
    }

    private function lockAndCheckDebitedLedgers(LedgerEntry $originalEntry): void
    {
        $creditedPostings = array_values(
            array_filter(
                $originalEntry->postings(),
                fn(Posting $posting): bool => $posting->side() === PostingSide::Credit,
            )
        );

        // A stable lock order prevents two simultaneous reversals from deadlocking.
        usort(
            $creditedPostings,
            fn(
                Posting $left,
                Posting $right
            ): int => $left->ledgerId()->value() <=> $right->ledgerId()->value()
        );

        foreach ($creditedPostings as $posting) {
            $balance = $this->balances->findForUpdate($posting->ledgerId())
                ?? LedgerBalance::zero($posting->ledgerId(), $posting->amount()->currency());

            if ($balance->balanceMinorUnits() < $posting->amount()->minorUnits()) {
                throw InsufficientReversalFunds::create();
            }
        }
    }
}
