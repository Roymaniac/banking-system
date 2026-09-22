<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;

/** Announces that withdrawn funds were posted successfully. */
final readonly class WithdrawalCompleted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        TransactionId $withdrawalId,
        DateTimeImmutable $occurredOn,
        private AccountId $accountId,
        private LedgerEntryId $ledgerEntryId,
        private TransactionReference $reference,
        private TransactionAmount $amount,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $withdrawalId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.withdrawal.completed';
    }

    public function amount(): TransactionAmount
    {
        return $this->amount;
    }

    /** @return array{account_id: string, ledger_entry_id: string, reference: string, currency: string} */
    public function payload(): array
    {
        // Exact amounts are available to trusted handlers, but omitted from general logs.
        return [
            'account_id' => $this->accountId->value(),
            'ledger_entry_id' => $this->ledgerEntryId->value(),
            'reference' => $this->reference->value(),
            'currency' => $this->amount->currency()->value(),
        ];
    }
}
