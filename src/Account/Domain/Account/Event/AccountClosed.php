<?php

declare(strict_types=1);

namespace Account\Domain\Account\Event;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\ClosureReason;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an account has been permanently closed. */
final readonly class AccountClosed extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private ClosureReason $reason,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $accountId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'account.closed';
    }

    /** @return array{reason: string} */
    public function payload(): array
    {
        return ['reason' => $this->reason->value];
    }
}
