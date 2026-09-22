<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Deposit\Event\DepositCompleted;

/** An immutable record proving that a deposit was posted successfully. */
final class Deposit extends AggregateRoot
{
    private function __construct(
        private readonly TransactionId $id,
        private readonly AccountId $accountId,
        private readonly LedgerEntryId $ledgerEntryId,
        private readonly TransactionReference $reference,
        private readonly TransactionAmount $amount,
        private readonly DateTimeImmutable $completedAt,
    ) {}

    public static function complete(
        TransactionId $id,
        AccountId $accountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $amount,
        DateTimeImmutable $completedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $deposit = new self($id, $accountId, $ledgerEntryId, $reference, $amount, $completedAt);
        $deposit->record(new DepositCompleted(
            $eventId,
            $id,
            $completedAt,
            $accountId,
            $ledgerEntryId,
            $reference,
            $amount,
            $correlationId,
        ));

        return $deposit;
    }

    /** Rebuilds a stored deposit without recording another completion event. */
    public static function reconstitute(
        TransactionId $id,
        AccountId $accountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $amount,
        DateTimeImmutable $completedAt,
        int $version,
    ): self {
        $deposit = new self($id, $accountId, $ledgerEntryId, $reference, $amount, $completedAt);
        $deposit->reconstituteAtVersion($version);

        return $deposit;
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
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
