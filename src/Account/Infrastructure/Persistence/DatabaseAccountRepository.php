<?php

declare(strict_types=1);

namespace Account\Infrastructure\Persistence;

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Account\Domain\Account\ValueObject\FreezeReason;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores accounts with Laravel's database connection. */
final readonly class DatabaseAccountRepository implements AccountRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Account $account): void
    {
        $storedVersion = $this->connection->table('accounts')
            ->where('id', $account->id()->value())
            ->value('version');

        $values = [
            'customer_id' => $account->customerId()->value(),
            'type' => $account->type()->value,
            'currency' => $account->currency()->value(),
            'number' => $account->number()?->value(),
            'status' => $account->status()->value,
            'freeze_reason' => $account->freezeReason()?->value,
            // Store timestamps in UTC so the same instant survives database
            // systems that do not preserve the original timezone offset.
            'frozen_at' => $account->frozenAt()?->setTimezone(new DateTimeZone('UTC')),
            'created_at' => $account->createdAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $account->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('accounts')->insert([
                'id' => $account->id()->value(),
                ...$values,
            ]);

            return;
        }

        $expectedVersion = $account->version() - 1;
        $updated = $this->connection->table('accounts')
            ->where('id', $account->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate(
                $account->id(),
                $expectedVersion,
                (int) $storedVersion,
            );
        }
    }

    public function findById(AccountId $id): ?Account
    {
        return $this->hydrate($this->connection->table('accounts')->where('id', $id->value())->first());
    }

    public function findByNumber(AccountNumber $number): ?Account
    {
        return $this->hydrate($this->connection->table('accounts')->where('number', $number->value())->first());
    }

    public function numberExists(AccountNumber $number): bool
    {
        return $this->connection->table('accounts')->where('number', $number->value())->exists();
    }

    public function findByCustomerId(CustomerId $customerId): array
    {
        return $this->connection->table('accounts')
            ->where('customer_id', $customerId->value())
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $record): Account => $this->hydrate($record))
            ->all();
    }

    private function hydrate(?object $record): ?Account
    {
        if ($record === null) {
            return null;
        }

        return Account::reconstitute(
            id: new AccountId($record->id),
            customerId: new CustomerId($record->customer_id),
            type: AccountType::from($record->type),
            currency: new CurrencyCode($record->currency),
            createdAt: new DateTimeImmutable($record->created_at),
            version: (int) $record->version,
            number: $record->number === null ? null : new AccountNumber($record->number),
            status: AccountStatus::from($record->status),
            freezeReason: $record->freeze_reason === null ? null : FreezeReason::from($record->freeze_reason),
            frozenAt: $record->frozen_at === null ? null : new DateTimeImmutable($record->frozen_at),
        );
    }
}
