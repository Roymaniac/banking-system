<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;

/** Announces that deposited funds were posted to the customer ledger. */
final readonly class DepositCompleted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        TransactionId $depositId,
        DateTimeImmutable $occurredOn,
        private AccountId $accountId,
        private LedgerEntryId $ledgerEntryId,
        private TransactionReference $reference,
        private TransactionAmount $amount,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $depositId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.deposit.completed';
    }

    public function amount(): TransactionAmount
    {
        return $this->amount;
    }

    /** @return array{account_id: string, ledger_entry_id: string, reference: string, currency: string} */
    public function payload(): array
    {
        // Exact amounts remain available to trusted handlers but are excluded
        // from generic logs and serialized event payloads.
        return [
            'account_id' => $this->accountId->value(),
            'ledger_entry_id' => $this->ledgerEntryId->value(),
            'reference' => $this->reference->value(),
            'currency' => $this->amount->currency()->value(),
        ];
    }
}
