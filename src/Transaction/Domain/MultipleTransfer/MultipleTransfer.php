<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\MultipleTransfer\Event\MultipleTransferCompleted;

/** An immutable record of several recipient payments completed together. */
final class MultipleTransfer extends AggregateRoot
{
    /** @param list<MultipleTransferItem> $items */
    private function __construct(
        private readonly TransactionId $id,
        private readonly AccountId $senderAccountId,
        private readonly LedgerEntryId $ledgerEntryId,
        private readonly TransactionReference $reference,
        private readonly TransactionAmount $totalAmount,
        private readonly array $items,
        private readonly DateTimeImmutable $completedAt,
    ) {}

    /** @param list<MultipleTransferItem> $items */
    public static function complete(
        TransactionId $id,
        AccountId $senderAccountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $totalAmount,
        array $items,
        DateTimeImmutable $completedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null
    ): self {
        $transfer = new self(
            $id,
            $senderAccountId,
            $ledgerEntryId,
            $reference,
            $totalAmount,
            $items,
            $completedAt
        );

        $transfer->record(new MultipleTransferCompleted(
            $eventId,
            $id,
            $completedAt,
            $senderAccountId,
            $ledgerEntryId,
            $reference,
            $totalAmount,
            count($items),
            $correlationId
        ));

        return $transfer;
    }

    /** @param list<MultipleTransferItem> $items */
    public static function reconstitute(
        TransactionId $id,
        AccountId $senderAccountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $totalAmount,
        array $items,
        DateTimeImmutable $completedAt,
        int $version
    ): self {
        $transfer = new self(
            $id,
            $senderAccountId,
            $ledgerEntryId,
            $reference,
            $totalAmount,
            $items,
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

    public function ledgerEntryId(): LedgerEntryId
    {
        return $this->ledgerEntryId;
    }

    public function reference(): TransactionReference
    {
        return $this->reference;
    }

    public function totalAmount(): TransactionAmount
    {
        return $this->totalAmount;
    }

    /** @return list<MultipleTransferItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
