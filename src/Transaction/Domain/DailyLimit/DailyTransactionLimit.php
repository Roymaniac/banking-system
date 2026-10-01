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
use Transaction\Domain\DailyLimit\Event\DailyTransactionLimitReduced;

/** The maximum total an account may send during one banking day. */
final class DailyTransactionLimit extends AggregateRoot
{
    private function __construct(
        private readonly AccountId $accountId,
        private readonly LedgerCurrency $currency,
        private readonly int $maximumMinorUnits,
        private readonly DateTimeImmutable $configuredAt,
        private ?int $customerMaximumMinorUnits = null,
    ) {
        if ($maximumMinorUnits <= 0) {
            throw new InvalidArgumentException('A daily transaction limit must be greater than zero.');
        }

        if ($customerMaximumMinorUnits !== null && ($customerMaximumMinorUnits <= 0 || $customerMaximumMinorUnits > $maximumMinorUnits)) {
            throw new InvalidArgumentException('A customer limit must be positive and cannot exceed the bank maximum.');
        }
    }

    public static function configure(
        AccountId $accountId,
        LedgerCurrency $currency,
        int $maximumMinorUnits,
        DateTimeImmutable $configuredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
        int $previousVersion = 0,
        ?int $customerMaximumMinorUnits = null,
    ): self {

        $limit = new self(
            $accountId,
            $currency,
            $maximumMinorUnits,
            $configuredAt,
            $customerMaximumMinorUnits
        );

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
        int $version,
        ?int $customerMaximumMinorUnits = null,
    ): self {

        $limit = new self(
            $accountId,
            $currency,
            $maximumMinorUnits,
            $configuredAt,
            $customerMaximumMinorUnits
        );

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

    public function customerMaximumMinorUnits(): ?int
    {
        return $this->customerMaximumMinorUnits;
    }

    public function effectiveMaximumMinorUnits(): int
    {
        return $this->customerMaximumMinorUnits ?? $this->maximumMinorUnits;
    }

    /** Allows a customer to lower, but never raise, their effective daily limit. */
    public function reduce(
        int $maximumMinorUnits,
        DateTimeImmutable $reducedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        if ($maximumMinorUnits <= 0 || $maximumMinorUnits > $this->effectiveMaximumMinorUnits()) {
            throw new InvalidArgumentException('A customer may only reduce the current daily transaction limit.');
        }

        $this->customerMaximumMinorUnits = $maximumMinorUnits;

        $this->record(
            new DailyTransactionLimitReduced(
                $eventId,
                $this->id(),
                $this->version() + 1,
                $reducedAt,
                $this->currency,
                $maximumMinorUnits,
                $correlationId,
            )
        );
    }
}
