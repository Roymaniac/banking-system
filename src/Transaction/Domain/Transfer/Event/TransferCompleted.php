<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Event;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;

/** Announces that an account-to-account transfer finished successfully. */
final readonly class TransferCompleted extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        TransactionId $transferId,
        DateTimeImmutable $occurredOn,
        private AccountId $senderAccountId,
        private AccountId $recipientAccountId,
        private LedgerEntryId $ledgerEntryId,
        private TransactionReference $reference,
        private TransactionAmount $amount,
        ?CorrelationId $correlationId = null,
    ) {
        parent::__construct($eventId, $transferId, 1, $occurredOn, $correlationId);
    }

    public static function eventName(): string
    {
        return 'transaction.transfer.completed';
    }

    public function amount(): TransactionAmount
    {
        return $this->amount;
    }

    /** @return array{sender_account_id: string, recipient_account_id: string, ledger_entry_id: string, reference: string, currency: string} */
    public function payload(): array
    {
        // The exact amount stays out of general event logs to reduce financial-data exposure.
        return [
            'sender_account_id' => $this->senderAccountId->value(),
            'recipient_account_id' => $this->recipientAccountId->value(),
            'ledger_entry_id' => $this->ledgerEntryId->value(),
            'reference' => $this->reference->value(),
            'currency' => $this->amount->currency()->value(),
        ];
    }
}
