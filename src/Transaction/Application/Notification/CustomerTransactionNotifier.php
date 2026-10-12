<?php

declare(strict_types=1);

namespace Transaction\Application\Notification;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Transaction\Domain\Common\ValueObject\TransactionAmount;

/** Records a durable customer message inside the money transaction. */
interface CustomerTransactionNotifier
{
    public function sendToAccount(
        AccountId $accountId,
        CustomerTransactionActivity $activity,
        TransactionAmount $amount,
        DateTimeImmutable $occurredAt,
    ): void;

    public function sendReversal(
        LedgerEntryId $originalEntryId,
        DateTimeImmutable $occurredAt,
    ): void;
}
