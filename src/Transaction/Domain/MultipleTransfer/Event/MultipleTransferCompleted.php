<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;

/** Announces that every payment in a multiple transfer completed. */
final readonly class MultipleTransferCompleted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        TransactionId $transferId,
        DateTimeImmutable $occurredOn,
        private AccountId $senderAccountId,
        private LedgerEntryId $ledgerEntryId,
        private TransactionReference $reference,
        private TransactionAmount $totalAmount,
        private int $recipientCount,
        ?CorrelationId $correlationId = null
    ) {
        parent::__construct($eventId, $transferId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.multiple_transfer.completed';
    }

    public function totalAmount(): TransactionAmount
    {
        return $this->totalAmount;
    }

    /** @return array{sender_account_id: string, ledger_entry_id: string, reference: string, currency: string, recipient_count: int} */
    public function payload(): array
    {
        // Recipient count is safe for general logs; exact monetary values are not included.
        return [
            'sender_account_id' => $this->senderAccountId->value(),
            'ledger_entry_id' => $this->ledgerEntryId->value(),
            'reference' => $this->reference->value(),
            'currency' => $this->totalAmount->currency()->value(),
            'recipient_count' => $this->recipientCount,
        ];
    }
}
