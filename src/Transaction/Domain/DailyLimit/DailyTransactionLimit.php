<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use InvalidArgumentException;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\DailyLimit\Event\DailyTransactionLimitConfigured;

/** The maximum total an account may send during one banking day. */
final class DailyTransactionLimit extends AggregateRoot
{
    private function __construct(
        private readonly AccountId $accountId,
        private readonly LedgerCurrency $currency,
        private readonly int $maximumMinorUnits,
        private readonly DateTimeImmutable $configuredAt,
    ) {
        if ($maximumMinorUnits <= 0) {
            throw new InvalidArgumentException('A daily transaction limit must be greater than zero.');
        }
    }

    public static function configure(
        AccountId $accountId,
        LedgerCurrency $currency,
        int $maximumMinorUnits,
        DateTimeImmutable $configuredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
        int $previousVersion = 0
    ): self {
        $limit = new self($accountId, $currency, $maximumMinorUnits, $configuredAt);

        $limit->reconstituteAtVersion($previousVersion);

        $limit->record(
            new DailyTransactionLimitConfigured(
                $eventId,
                $accountId,
                $previousVersion + 1,
                $configuredAt,
                $currency,
                $maximumMinorUnits,
                $correlationId
            )
        );

        return $limit;
    }

    /** Rebuilds a saved limit without recording another configuration event. */
    public static function reconstitute(
        AccountId $accountId,
        LedgerCurrency $currency,
        int $maximumMinorUnits,
        DateTimeImmutable $configuredAt,
        int $version
    ): self {
        $limit = new self($accountId, $currency, $maximumMinorUnits, $configuredAt);
        $limit->reconstituteAtVersion($version);

        return $limit;
    }

    public function id(): AccountId
    {
        return $this->accountId;
    }

    public function currency(): LedgerCurrency
    {
        return $this->currency;
    }

    public function maximumMinorUnits(): int
    {
        return $this->maximumMinorUnits;
    }

    public function configuredAt(): DateTimeImmutable
    {
        return $this->configuredAt;
    }
}
