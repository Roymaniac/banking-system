<?php

declare(strict_types=1);

namespace Account\Domain\Account\Event;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that the bank created an account record for a customer. */
final readonly class AccountCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        AccountId $accountId,
        DateTimeImmutable $occurredOn,
        private CustomerId $customerId,
        private AccountType $accountType,
        private CurrencyCode $currency,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $accountId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'account.created';
    }

    /** @return array{customer_id: string, account_type: string, currency: string} */
    public function payload(): array
    {
        // The event contains operational identifiers, never customer profile data.
        return [
            'customer_id' => $this->customerId->value(),
            'account_type' => $this->accountType->value,
            'currency' => $this->currency->value(),
        ];
    }
}
