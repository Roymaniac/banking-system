<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Withdrawal\Event\WithdrawalCompleted;

/** An immutable record proving that a withdrawal was posted successfully. */
final class Withdrawal extends AggregateRoot
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
        ?CorrelationId $correlationId = null
    ): self {
        $withdrawal = new self($id, $accountId, $ledgerEntryId, $reference, $amount, $completedAt);
        $withdrawal->record(new WithdrawalCompleted(
            $eventId,
            $id,
            $completedAt,
            $accountId,
            $ledgerEntryId,
            $reference,
            $amount,
            $correlationId
        ));

        return $withdrawal;
    }

    /** Rebuilds a stored withdrawal without recording another completion event. */
    public static function reconstitute(
        TransactionId $id,
        AccountId $accountId,
        LedgerEntryId $ledgerEntryId,
        TransactionReference $reference,
        TransactionAmount $amount,
        DateTimeImmutable $completedAt,
        int $version
    ): self {
        $withdrawal = new self($id, $accountId, $ledgerEntryId, $reference, $amount, $completedAt);
        $withdrawal->reconstituteAtVersion($version);

        return $withdrawal;
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
