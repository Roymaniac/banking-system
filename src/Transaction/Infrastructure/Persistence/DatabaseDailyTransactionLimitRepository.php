<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Persistence;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Exception\ConcurrencyException;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Exception\DailyLimitCurrencyMismatch;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitExceeded;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;

/** Persists limits and serializes daily spending updates per account. */
final readonly class DatabaseDailyTransactionLimitRepository implements DailyTransactionLimitRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(DailyTransactionLimit $limit): void
    {
        $storedVersion = $this->connection->table('daily_transaction_limits')
            ->where('account_id', $limit->id()->value())
            ->value('version');

        $values = [
            'currency' => $limit->currency()->value(),
            'maximum_minor_units' => $limit->maximumMinorUnits(),
            'configured_at' => $limit->configuredAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $limit->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('daily_transaction_limits')
                ->insert(['account_id' => $limit->id()->value(), ...$values]);

            return;
        }

        $expectedVersion = $limit->version() - 1;
        $updated = $this->connection->table('daily_transaction_limits')
            ->where('account_id', $limit->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($limit->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function find(AccountId $accountId): ?DailyTransactionLimit
    {
        $record = $this->connection->table('daily_transaction_limits')
            ->where('account_id', $accountId->value())
            ->first();

        if ($record === null) {
            return null;
        }

        return DailyTransactionLimit::reconstitute(
            $accountId,
            new LedgerCurrency($record->currency),
            (int) $record->maximum_minor_units,
            new DateTimeImmutable($record->configured_at),
            (int) $record->version
        );
    }

    public function consume(
        AccountId $accountId,
        LedgerCurrency $currency,
        int $minorUnits,
        DateTimeImmutable $bankingTime
    ): void {
        // Locking the limit row serializes every outgoing request for this account,
        // including the first request of a new day when no usage row exists yet.
        $limit = $this->connection->table('daily_transaction_limits')
            ->where('account_id', $accountId->value())
            ->lockForUpdate()
            ->first();

        if ($limit === null) {
            return;
        }

        if ($limit->currency !== $currency->value()) {
            throw DailyLimitCurrencyMismatch::create();
        }

        $date = $bankingTime->format('Y-m-d');
        $used = (int) ($this->connection->table('daily_transaction_limit_usages')
            ->where('account_id', $accountId->value())
            ->where('usage_date', $date)
            ->value('used_minor_units') ?? 0);

        if ($minorUnits <= 0 || $used > (int) $limit->maximum_minor_units - $minorUnits) {
            throw DailyTransactionLimitExceeded::create();
        }

        $this->connection->table('daily_transaction_limit_usages')
            ->updateOrInsert(
                [
                    'account_id' => $accountId->value(),
                    'usage_date' => $date
                ],
                [
                    'currency' => $currency->value(),
                    'used_minor_units' => $used + $minorUnits,
                    'updated_at' => $bankingTime->setTimezone(new DateTimeZone('UTC'))
                ],
            );
    }
}
