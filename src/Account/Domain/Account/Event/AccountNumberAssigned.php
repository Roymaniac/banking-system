<?php

declare(strict_types=1);

namespace Account\Domain\Account\Event;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that a customer-facing number was assigned to an account. */
final readonly class AccountNumberAssigned extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        int $aggregateVersion,
        DateTimeImmutable $occurredOn,
        private AccountNumber $accountNumber,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $accountId, $aggregateVersion, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'account.number.assigned';
    }

    /** @return array{last_four: string} */
    public function payload(): array
    {
        // Events expose only the last four digits to reduce unnecessary
        // distribution of full customer-facing account numbers.
        return ['last_four' => $this->accountNumber->lastFour()];
    }
}
