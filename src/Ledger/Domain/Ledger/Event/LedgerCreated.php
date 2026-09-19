<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/** Announces that an account now has its authoritative financial ledger. */
final readonly class LedgerCreated extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        LedgerId $ledgerId,
        DateTimeImmutable $occurredOn,
        private AccountId $accountId,
        private LedgerCurrency $currency,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $ledgerId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'ledger.created';
    }

    /** @return array{account_id: string, currency: string} */
    public function payload(): array
    {
        return [
            'account_id' => $this->accountId->value(),
            'currency' => $this->currency->value(),
        ];
    }
}
