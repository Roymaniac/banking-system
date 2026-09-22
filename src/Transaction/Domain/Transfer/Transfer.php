<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Transfer\Event\TransferCompleted;

/** An immutable record proving that money moved between two accounts. */
final class Transfer extends AggregateRoot
{
    private function __construct(
        private readonly TransactionId $id,
        private readonly AccountId $senderAccountId,
        private readonly AccountId $recipientAccountId,
        private readonly LedgerEntryId $ledgerEntryId,
        private readonly TransactionReference $reference,
        private readonly TransactionAmount $amount,
        private readonly DateTimeImmutable $completedAt,
    ) {}

    public static function complete(
        TransactionId $id,
        AccountId $senderAccountId,
        AccountId $recipientAccountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $amount,
        DateTimeImmutable $completedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $transfer = new self(
            $id,
            $senderAccountId,
            $recipientAccountId,
            $ledgerEntryId,
            $reference,
            $amount,
            $completedAt
        );

        $transfer->record(
            new TransferCompleted(
                $eventId,
                $id,
                $completedAt,
                $senderAccountId,
                $recipientAccountId,
                $ledgerEntryId,
                $reference,
                $amount,
                $correlationId
            )
        );

        return $transfer;
    }

    /** Rebuilds a stored transfer without announcing it as a new transfer. */
    public static function reconstitute(
        TransactionId $id,
        AccountId $senderAccountId,
        AccountId $recipientAccountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $amount,
        DateTimeImmutable $completedAt,
        int $version
    ): self {
        $transfer = new self(
            $id,
            $senderAccountId,
            $recipientAccountId,
            $ledgerEntryId,
            $reference,
            $amount,
            $completedAt
        );

        $transfer->reconstituteAtVersion($version);

        return $transfer;
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function senderAccountId(): AccountId
    {
        return $this->senderAccountId;
    }

    public function recipientAccountId(): AccountId
    {
        return $this->recipientAccountId;
    }

    public function ledgerEntryId(): LedgerEntryId
    {
        return $this->ledgerEntryId;
    }

    public function reference(): TransactionReference
    {
        return $this->reference;
    }

    public function amount(): TransactionAmount
    {
        return $this->amount;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
