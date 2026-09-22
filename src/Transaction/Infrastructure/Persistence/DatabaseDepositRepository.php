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
use Transaction\Domain\Deposit\Deposit;
use Transaction\Domain\Deposit\Repository\DepositRepository;

/** Stores immutable completed-deposit records. */
final readonly class DatabaseDepositRepository implements DepositRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Deposit $deposit): void
    {
        $storedVersion = $this->connection->table('deposits')
            ->where('id', $deposit->id()->value())
            ->value('version');
        $values = [
            'account_id' => $deposit->accountId()->value(),
            'ledger_entry_id' => $deposit->ledgerEntryId()->value(),
            'reference' => $deposit->reference()->value(),
            'minor_units' => $deposit->amount()->minorUnits(),
            'currency' => $deposit->amount()->currency()->value(),
            'completed_at' => $deposit->completedAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $deposit->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('deposits')->insert([
                'id' => $deposit->id()->value(),
                ...$values,
            ]);

            return;
        }

        $expectedVersion = $deposit->version() - 1;
        $updated = $this->connection->table('deposits')
            ->where('id', $deposit->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($deposit->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(TransactionId $id): ?Deposit
    {
        return $this->hydrate($this->connection->table('deposits')->where('id', $id->value())->first());
    }

    public function findByReference(TransactionReference $reference): ?Deposit
    {
        return $this->hydrate($this->connection->table('deposits')->where('reference', $reference->value())->first());
    }

    public function referenceExists(TransactionReference $reference): bool
    {
        return $this->connection->table('deposits')->where('reference', $reference->value())->exists();
    }

    private function hydrate(?object $record): ?Deposit
    {
        if ($record === null) {
            return null;
        }

        return Deposit::reconstitute(
            new TransactionId($record->id),
            new AccountId($record->account_id),
            new LedgerEntryId($record->ledger_entry_id),
            new TransactionReference($record->reference),
            new TransactionAmount((int) $record->minor_units, new LedgerCurrency($record->currency)),
            new DateTimeImmutable($record->completed_at),
            (int) $record->version,
        );
    }
}
