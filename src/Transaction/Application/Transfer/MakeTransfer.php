<?php

declare(strict_types=1);

namespace Transaction\Application\Transfer;

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
use Transaction\Domain\Transfer\Exception\AccountNotEligibleForTransfer;
use Transaction\Domain\Transfer\Exception\DuplicateTransferReference;
use Transaction\Domain\Transfer\Exception\InsufficientTransferFunds;
use Transaction\Domain\Transfer\Exception\SameAccountTransfer;
use Transaction\Domain\Transfer\Exception\TransferCurrencyMismatch;
use Transaction\Domain\Transfer\Exception\TransferLedgerUnavailable;
use Transaction\Domain\Transfer\Repository\TransferRepository;
use Transaction\Domain\Transfer\Transfer;

/** Moves money between customer accounts without creating or destroying value. */
final readonly class MakeTransfer
{
    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private LedgerEntryRepository $entries,
        private TransferRepository $transfers,
        private BalanceProjectionRepository $balances,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(MakeTransferCommand $command): Transfer
    {
        if ($command->senderAccountId->equals($command->recipientAccountId)) {
            throw SameAccountTransfer::create();
        }

        $sender = $this->accounts->findById($command->senderAccountId);
        $recipient = $this->accounts->findById($command->recipientAccountId);

        if ($sender === null || $recipient === null || ! $sender->isActive() || ! $recipient->isActive()) {
            throw AccountNotEligibleForTransfer::create();
        }

        $senderLedger = $this->ledgers->findByAccountId($sender->id());
        $recipientLedger = $this->ledgers->findByAccountId($recipient->id());

        if ($senderLedger === null || $recipientLedger === null) {
            throw TransferLedgerUnavailable::create();
        }

        if (
            $sender->currency()->value() !== $senderLedger->currency()->value()
            || $recipient->currency()->value() !== $recipientLedger->currency()->value()
            || ! $senderLedger->currency()->equals($recipientLedger->currency())
        ) {
            throw TransferCurrencyMismatch::create();
        }

        $reference = new TransactionReference($command->reference);

        if ($this->transfers->referenceExists($reference)) {
            throw DuplicateTransferReference::create();
        }

        [$transfer, $domainEvents] = $this->transactions->run(function () use (
            $command,
            $sender,
            $recipient,
            $senderLedger,
            $recipientLedger,
            $reference
        ): array {
            // Lock the sender's balance so two requests cannot spend the same money.
            $balance = $this->balances->findForUpdate($senderLedger->id())
                ?? LedgerBalance::zero($senderLedger->id(), $senderLedger->currency());

            if ($balance->balanceMinorUnits() < $command->minorUnits) {
                throw InsufficientTransferFunds::create();
            }

            $now = $this->clock->now();
            $entry = LedgerEntry::draft(
                new LedgerEntryId($this->uuidGenerator->generate()->value()),
                $senderLedger->id(),
                new EntryReference($reference->value()),
                new EntryDescription('Transfer ' . $reference->value()),
                $command->occurredAt,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId,
            );
            $amount = new PostingAmount($command->minorUnits, $senderLedger->currency());

            $entry->addPosting(
                new PostingId($this->uuidGenerator->generate()->value()),
                $senderLedger->id(),
                PostingSide::Debit,
                $amount,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $entry->addPosting(
                new PostingId($this->uuidGenerator->generate()->value()),
                $recipientLedger->id(),
                PostingSide::Credit,
                $amount,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $entry->post($now, $this->uuidGenerator->generate(), $command->correlationId);

            $transfer = Transfer::complete(
                new TransactionId($this->uuidGenerator->generate()->value()),
                $sender->id(),
                $recipient->id(),
                $entry->id(),
                $reference,
                new TransactionAmount($command->minorUnits, $senderLedger->currency()),
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $this->entries->save($entry);
            $this->transfers->save($transfer);
            // Both account balances become visible together before the transaction commits.
            $this->balances->apply($entry, $now);

            return [$transfer, [...$entry->pullDomainEvents(), ...$transfer->pullDomainEvents()]];
        });

        $this->events->publish($domainEvents);

        return $transfer;
    }
}
