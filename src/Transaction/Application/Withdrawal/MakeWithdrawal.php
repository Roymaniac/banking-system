<?php

declare(strict_types=1);

namespace Transaction\Application\Withdrawal;

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
use Transaction\Domain\Withdrawal\Exception\AccountNotEligibleForWithdrawal;
use Transaction\Domain\Withdrawal\Exception\DuplicateWithdrawalReference;
use Transaction\Domain\Withdrawal\Exception\InsufficientFunds;
use Transaction\Domain\Withdrawal\Exception\WithdrawalCurrencyMismatch;
use Transaction\Domain\Withdrawal\Exception\WithdrawalLedgerUnavailable;
use Transaction\Domain\Withdrawal\Repository\WithdrawalRepository;
use Transaction\Domain\Withdrawal\Withdrawal;

/** Safely checks funds and posts a balanced withdrawal as one atomic change. */
final readonly class MakeWithdrawal
{
    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private LedgerEntryRepository $entries,
        private WithdrawalRepository $withdrawals,
        private BalanceProjectionRepository $balances,
        private DailyTransactionLimitRepository $dailyLimits,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(MakeWithdrawalCommand $command): Withdrawal
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null || ! $account->isActive()) {
            throw AccountNotEligibleForWithdrawal::create();
        }

        $customerLedger = $this->ledgers->findByAccountId($command->accountId);
        $disbursementLedger = $this->ledgers->findById($command->disbursementLedgerId);

        if ($customerLedger === null || $disbursementLedger === null) {
            throw WithdrawalLedgerUnavailable::create();
        }

        if (
            $account->currency()->value() !== $customerLedger->currency()->value()
            || ! $customerLedger->currency()->equals($disbursementLedger->currency())
        ) {
            throw WithdrawalCurrencyMismatch::create();
        }

        $reference = new TransactionReference($command->reference);

        if ($this->withdrawals->referenceExists($reference)) {
            throw DuplicateWithdrawalReference::create();
        }

        [$withdrawal, $domainEvents] = $this->transactions->run(function () use (
            $command,
            $account,
            $customerLedger,
            $disbursementLedger,
            $reference
        ): array {
            $lockedAccount = $this->accounts->findByIdForUpdate($account->id());

            if ($lockedAccount === null || ! $lockedAccount->isActive()) {
                throw AccountNotEligibleForWithdrawal::create();
            }

            // The lock stops two simultaneous withdrawals from spending the same balance.
            $balance = $this->balances->findForUpdate($customerLedger->id())
                ?? LedgerBalance::zero($customerLedger->id(), $customerLedger->currency());

            if ($balance->balanceMinorUnits() < $command->minorUnits) {
                throw InsufficientFunds::create();
            }

            $now = $this->clock->now();
            // Reserving today's allowance here prevents concurrent requests bypassing it.
            $this->dailyLimits->consume(
                $account->id(),
                $customerLedger->currency(),
                $command->minorUnits,
                $now
            );

            $entry = LedgerEntry::draft(
                new LedgerEntryId($this->uuidGenerator->generate()->value()),
                $customerLedger->id(),
                new EntryReference($reference->value()),
                new EntryDescription('Withdrawal '.$reference->value()),
                $command->occurredAt,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $amount = new PostingAmount($command->minorUnits, $customerLedger->currency());

            $entry->addPosting(
                new PostingId($this->uuidGenerator->generate()->value()),
                $customerLedger->id(),
                PostingSide::Debit,
                $amount,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $entry->addPosting(
                new PostingId($this->uuidGenerator->generate()->value()),
                $disbursementLedger->id(),
                PostingSide::Credit,
                $amount,
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $entry->post($now, $this->uuidGenerator->generate(), $command->correlationId);

            $withdrawal = Withdrawal::complete(
                new TransactionId($this->uuidGenerator->generate()->value()),
                $account->id(),
                $entry->id(),
                $reference,
                new TransactionAmount($command->minorUnits, $customerLedger->currency()),
                $now,
                $this->uuidGenerator->generate(),
                $command->correlationId
            );

            $this->entries->save($entry);
            $this->withdrawals->save($withdrawal);
            // Update the locked balance before committing. Event replay is safely ignored.
            $this->balances->apply($entry, $now);

            return [$withdrawal, [...$entry->pullDomainEvents(), ...$withdrawal->pullDomainEvents()]];
        });

        $this->events->publish($domainEvents);

        return $withdrawal;
    }
}
