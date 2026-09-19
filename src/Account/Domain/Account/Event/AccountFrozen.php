<?php

declare(strict_types=1);

namespace Account\Domain\Account\Event;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\FreezeReason;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that activity on an account has been restricted. */
final readonly class AccountFrozen extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private FreezeReason $reason,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $accountId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'account.frozen';
    }

    /** @return array{reason: string} */
    public function payload(): array
    {
        // A controlled reason is safe for audit consumers and avoids free-text
        // notes accidentally carrying private customer information.
        return ['reason' => $this->reason->value];
    }
}
