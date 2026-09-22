<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer;

use Account\Domain\Account\ValueObject\AccountId;
use Transaction\Domain\Common\ValueObject\TransactionAmount;

/** One recipient payment contained in a completed multiple transfer. */
final readonly class MultipleTransferItem
{
    public function __construct(
        private AccountId $recipientAccountId,
        private TransactionAmount $amount,
    ) {}

    public function recipientAccountId(): AccountId
    {
        return $this->recipientAccountId;
    }

    public function amount(): TransactionAmount
    {
        return $this->amount;
    }
}
