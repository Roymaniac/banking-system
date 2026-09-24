<?php

declare(strict_types=1);

namespace Transaction\Application\MultipleTransfer;

use Account\Domain\Account\Repository\AccountRepository;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;
use Transaction\Domain\MultipleTransfer\Exception\AccountNotEligibleForMultipleTransfer;
use Transaction\Domain\MultipleTransfer\Exception\DuplicateMultipleTransferReference;
use Transaction\Domain\MultipleTransfer\Exception\InsufficientMultipleTransferFunds;
use Transaction\Domain\MultipleTransfer\Exception\InvalidMultipleTransfer;
use Transaction\Domain\MultipleTransfer\Exception\MultipleTransferCurrencyMismatch;
use Transaction\Domain\MultipleTransfer\Exception\MultipleTransferLedgerUnavailable;
use Transaction\Domain\MultipleTransfer\MultipleTransfer;
use Transaction\Domain\MultipleTransfer\MultipleTransferItem;
use Transaction\Domain\MultipleTransfer\Repository\MultipleTransferRepository;

/** Pays several recipients through one balanced, all-or-nothing ledger entry. */
final readonly class MakeMultipleTransfer
{
    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private LedgerEntryRepository $entries,
        private MultipleTransferRepository $multipleTransfers,
        private BalanceProjectionRepository $balances,
        private DailyTransactionLimitRepository $dailyLimits,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(MakeMultipleTransferCommand $command): MultipleTransfer
    {
        if ($command->recipients === []) {
            throw InvalidMultipleTransfer::empty();
        }

        $sender = $this->accounts->findById($command->senderAccountId);

        if ($sender === null || ! $sender->isActive()) {
            throw AccountNotEligibleForMultipleTransfer::create();
        }

        $senderLedger = $this->ledgers->findByAccountId($sender->id());

        if ($senderLedger === null) {
            throw MultipleTransferLedgerUnavailable::create();
        }

        if ($sender->currency()->value() !== $senderLedger->currency()->value()) {
            throw MultipleTransferCurrencyMismatch::create();
        }

        $recipientLedgers = [];
        $items = [];
        $seenRecipients = [];
        $totalMinorUnits = 0;

        foreach ($command->recipients as $recipientRequest) {
            $recipientId = $recipientRequest->accountId->value();

            if ($sender->id()->equals($recipientRequest->accountId)) {
                throw InvalidMultipleTransfer::senderIsRecipient();
            }

            if (isset($seenRecipients[$recipientId])) {
                throw InvalidMultipleTransfer::duplicateRecipient();
            }

            $seenRecipients[$recipientId] = true;
            $recipient = $this->accounts->findById($recipientRequest->accountId);

            if ($recipient === null || ! $recipient->isActive()) {
                throw AccountNotEligibleForMultipleTransfer::create();
            }

            $recipientLedger = $this->ledgers->findByAccountId($recipient->id());

            if ($recipientLedger === null) {
                throw MultipleTransferLedgerUnavailable::create();
            }

            if (
                $recipient->currency()->value() !== $recipientLedger->currency()->value()
                || ! $senderLedger->currency()->equals($recipientLedger->currency())
            ) {
                throw MultipleTransferCurrencyMismatch::create();
            }

            // Constructing the amount here rejects zero or negative recipient payments.
            $amount = new TransactionAmount($recipientRequest->minorUnits, $senderLedger->currency());
            $totalMinorUnits += $recipientRequest->minorUnits;
            $recipientLedgers[] = [$recipientLedger, $amount];
            $items[] = new MultipleTransferItem($recipient->id(), $amount);
        }

        $reference = new TransactionReference($command->reference);

        if ($this->multipleTransfers->referenceExists($reference)) {
            throw DuplicateMultipleTransferReference::create();
        }

        [$transfer, $domainEvents] = $this->transactions->run(function () use (
            $command,
            $sender,
            $senderLedger,
            $recipientLedgers,
            $items,
            $totalMinorUnits,
            $reference
        ): array {
            $accountIds = [$sender->id(), ...array_map(fn ($item) => $item->recipientAccountId(), $items)];
            usort($accountIds, fn ($left, $right): int => $left->value() <=> $right->value());

            // Every account uses the same stable lock order, preventing closure
            // races and deadlocks between overlapping batch transfers.
            foreach ($accountIds as $accountId) {
                $lockedAccount = $this->accounts->findByIdForUpdate($accountId);

                if ($lockedAccount === null || ! $lockedAccount->isActive()) {
                    throw AccountNotEligibleForMultipleTransfer::create();
                }
            }

            // One balance lock reserves the whole batch total before any recipient is paid.
            $balance = $this->balances->findForUpdate($senderLedger->id())
                ?? LedgerBalance::zero($senderLedger->id(), $senderLedger->currency());

            if ($balance->balanceMinorUnits() < $totalMinorUnits) {
                throw InsufficientMultipleTransferFunds::create();
            }

            $now = $this->clock->now();
            // The batch consumes its complete total once, not once per recipient.
            $this->dailyLimits->consume(
                $sender->id(),
                $senderLedger->currency(),
                $totalMinorUnits,
                $now
            );

            $entry = LedgerEntry::draft(
                new LedgerEntryId($this->uuidGenerator->generate()->value()),
                $senderLedger->id(),
                new EntryReference($reference->value()),
                new EntryDescription('Multiple transfer '.$reference->value()),
                $command->occurredAt,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $entry->addPosting(
                new PostingId($this->uuidGenerator->generate()->value()),
                $senderLedger->id(),
                PostingSide::Debit,
                new PostingAmount($totalMinorUnits, $senderLedger->currency()),
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            foreach ($recipientLedgers as [$recipientLedger, $amount]) {
                $entry->addPosting(
                    new PostingId($this->uuidGenerator->generate()->value()),
                    $recipientLedger->id(),
                    PostingSide::Credit,
                    new PostingAmount($amount->minorUnits(), $amount->currency()),
                    $now,
                    $this->uuidGenerator->generate(),
                    $command->correlationId
                );
            }

            $entry->post($now, $this->uuidGenerator->generate(), $command->correlationId);

            $transfer = MultipleTransfer::complete(
                new TransactionId($this->uuidGenerator->generate()->value()),
                $sender->id(),
                $entry->id(),
                $reference,
                new TransactionAmount($totalMinorUnits, $senderLedger->currency()),
                $items,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $this->entries->save($entry);
            $this->multipleTransfers->save($transfer);
            // The sender debit and all recipient credits become visible together.
            $this->balances->apply($entry, $now);

            return [$transfer, [...$entry->pullDomainEvents(), ...$transfer->pullDomainEvents()]];
        });

        $this->events->publish($domainEvents);

        return $transfer;
    }
}
