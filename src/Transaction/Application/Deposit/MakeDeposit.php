<?php

declare(strict_types=1);

namespace Transaction\Application\Deposit;

use Account\Domain\Account\Repository\AccountRepository;
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
use Transaction\Domain\Deposit\Deposit;
use Transaction\Domain\Deposit\Exception\AccountNotEligibleForDeposit;
use Transaction\Domain\Deposit\Exception\DepositCurrencyMismatch;
use Transaction\Domain\Deposit\Exception\DepositLedgerUnavailable;
use Transaction\Domain\Deposit\Exception\DuplicateDepositReference;
use Transaction\Domain\Deposit\Repository\DepositRepository;

/** Posts a balanced deposit and its transaction record as one atomic change. */
final readonly class MakeDeposit
{
    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private LedgerEntryRepository $entries,
        private DepositRepository $deposits,
        private BalanceProjectionRepository $balances,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(MakeDepositCommand $command): Deposit
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null || ! $account->isActive()) {
            throw AccountNotEligibleForDeposit::create();
        }

        $customerLedger = $this->ledgers->findByAccountId($command->accountId);
        $fundingLedger = $this->ledgers->findById($command->fundingLedgerId);

        if ($customerLedger === null || $fundingLedger === null) {
            throw DepositLedgerUnavailable::create();
        }

        if (
            $account->currency()->value() !== $customerLedger->currency()->value()
            || ! $customerLedger->currency()->equals($fundingLedger->currency())
        ) {
            throw DepositCurrencyMismatch::create();
        }

        $reference = new TransactionReference($command->reference);

        if ($this->deposits->referenceExists($reference)) {
            throw DuplicateDepositReference::create();
        }

        $now = $this->clock->now();
        $entry = LedgerEntry::draft(
            new LedgerEntryId($this->uuidGenerator->generate()->value()),
            $customerLedger->id(),
            new EntryReference($reference->value()),
            new EntryDescription('Deposit ' . $reference->value()),
            $command->occurredAt,
            $now,
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );
        $postingAmount = new PostingAmount($command->minorUnits, $customerLedger->currency());
        $entry->addPosting(
            new PostingId($this->uuidGenerator->generate()->value()),
            $fundingLedger->id(),
            PostingSide::Debit,
            $postingAmount,
            $now,
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );
        $entry->addPosting(
            new PostingId($this->uuidGenerator->generate()->value()),
            $customerLedger->id(),
            PostingSide::Credit,
            $postingAmount,
            $now,
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );
        $entry->post($now, $this->uuidGenerator->generate(), $command->correlationId);

        $deposit = Deposit::complete(
            new TransactionId($this->uuidGenerator->generate()->value()),
            $account->id(),
            $entry->id(),
            $reference,
            new TransactionAmount($command->minorUnits, $customerLedger->currency()),
            $now,
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );

        $domainEvents = $this->transactions->run(
            function () use ($command, $entry, $deposit, $now): array {
                $lockedAccount = $this->accounts->findByIdForUpdate($command->accountId);

                if ($lockedAccount === null || ! $lockedAccount->isActive()) {
                    throw AccountNotEligibleForDeposit::create();
                }

                $this->entries->save($entry);
                $this->deposits->save($deposit);
                // Project before commit so a completed deposit can never exist
                // without its spendable balance, even if event delivery later fails.
                $this->balances->apply($entry, $now);

                return [...$entry->pullDomainEvents(), ...$deposit->pullDomainEvents()];
            }
        );

        $this->events->publish($domainEvents);

        return $deposit;
    }
}
