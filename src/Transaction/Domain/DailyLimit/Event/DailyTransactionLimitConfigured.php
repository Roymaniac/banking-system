<?php

declare(strict_types=1);

namespace Transaction\Domain\DailyLimit\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an account's daily outgoing limit was configured. */
final readonly class DailyTransactionLimitConfigured extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private LedgerCurrency $currency,
        private int $maximumMinorUnits,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $accountId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.daily_limit.configured';
    }

    public function maximumMinorUnits(): int
    {
        return $this->maximumMinorUnits;
    }

    /** @return array{currency: string} */
    public function payload(): array
    {
        // The exact limit is available to trusted handlers but omitted from general logs.
        return ['currency' => $this->currency->value()];
    }
}
