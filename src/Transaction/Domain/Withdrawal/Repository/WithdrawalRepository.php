<?php

declare(strict_types=1);

namespace Transaction\Domain\Withdrawal\Repository;

use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Withdrawal\Withdrawal;

/** Defines storage operations for completed withdrawals. */
interface WithdrawalRepository
{
    public function save(Withdrawal $withdrawal): void;

    public function findById(TransactionId $id): ?Withdrawal;

    public function findByReference(TransactionReference $reference): ?Withdrawal;

    public function referenceExists(TransactionReference $reference): bool;
}
