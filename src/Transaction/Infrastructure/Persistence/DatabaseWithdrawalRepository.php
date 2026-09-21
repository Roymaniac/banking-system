<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Persistence;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Exception\ConcurrencyException;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Withdrawal\Repository\WithdrawalRepository;
use Transaction\Domain\Withdrawal\Withdrawal;

/** Stores immutable completed-withdrawal records. */
final readonly class DatabaseWithdrawalRepository implements WithdrawalRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Withdrawal $withdrawal): void
    {
        $storedVersion = $this->connection->table('withdrawals')
            ->where('id', $withdrawal->id()->value())->value('version');

        $values = [
            'account_id' => $withdrawal->accountId()->value(),
            'ledger_entry_id' => $withdrawal->ledgerEntryId()->value(),
            'reference' => $withdrawal->reference()->value(),
            'minor_units' => $withdrawal->amount()->minorUnits(),
            'currency' => $withdrawal->amount()->currency()->value(),
            'completed_at' => $withdrawal->completedAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $withdrawal->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('withdrawals')
                ->insert(['id' => $withdrawal->id()->value(), ...$values]);

            return;
        }

        $expectedVersion = $withdrawal->version() - 1;
        $updated = $this->connection->table('withdrawals')
            ->where('id', $withdrawal->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($withdrawal->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(TransactionId $id): ?Withdrawal
    {
        return $this->hydrate($this->connection->table('withdrawals')->where('id', $id->value())->first());
    }

    public function findByReference(TransactionReference $reference): ?Withdrawal
    {
        return $this->hydrate($this->connection->table('withdrawals')->where('reference', $reference->value())->first());
    }

    public function referenceExists(TransactionReference $reference): bool
    {
        return $this->connection->table('withdrawals')->where('reference', $reference->value())->exists();
    }

    private function hydrate(?object $record): ?Withdrawal
    {
        if ($record === null) {
            return null;
        }

        return Withdrawal::reconstitute(
            new TransactionId($record->id),
            new AccountId($record->account_id),
            new LedgerEntryId($record->ledger_entry_id),
            new TransactionReference($record->reference),
            new TransactionAmount((int) $record->minor_units, new LedgerCurrency($record->currency)),
            new DateTimeImmutable($record->completed_at),
            (int) $record->version
        );
    }
}
