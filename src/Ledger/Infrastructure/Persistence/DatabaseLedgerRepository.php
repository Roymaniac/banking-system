<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Persistence;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores ledgers with Laravel's database connection. */
final readonly class DatabaseLedgerRepository implements LedgerRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Ledger $ledger): void
    {
        $storedVersion = $this->connection->table('ledgers')
            ->where('id', $ledger->id()->value())
            ->value('version');
        $values = [
            'account_id' => $ledger->accountId()->value(),
            'currency' => $ledger->currency()->value(),
            'created_at' => $ledger->createdAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $ledger->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('ledgers')->insert([
                'id' => $ledger->id()->value(),
                ...$values,
            ]);

            return;
        }

        $expectedVersion = $ledger->version() - 1;
        $updated = $this->connection->table('ledgers')
            ->where('id', $ledger->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate(
                $ledger->id(),
                $expectedVersion,
                (int) $storedVersion,
            );
        }
    }

    public function findById(LedgerId $id): ?Ledger
    {
        return $this->hydrate($this->connection->table('ledgers')->where('id', $id->value())->first());
    }

    public function findByAccountId(AccountId $accountId): ?Ledger
    {
        return $this->hydrate($this->connection->table('ledgers')->where('account_id', $accountId->value())->first());
    }

    public function existsForAccount(AccountId $accountId): bool
    {
        return $this->connection->table('ledgers')->where('account_id', $accountId->value())->exists();
    }

    private function hydrate(?object $record): ?Ledger
    {
        if ($record === null) {
            return null;
        }

        return Ledger::reconstitute(
            id: new LedgerId($record->id),
            accountId: new AccountId($record->account_id),
            currency: new LedgerCurrency($record->currency),
            createdAt: new DateTimeImmutable($record->created_at),
            version: (int) $record->version,
        );
    }
}
