<?php

declare(strict_types=1);

namespace Transaction\Domain\Deposit\Repository;

use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Deposit\Deposit;

/** Defines storage operations for completed deposits. */
interface DepositRepository
{
    public function save(Deposit $deposit): void;

    public function findById(TransactionId $id): ?Deposit;

    public function findByReference(TransactionReference $reference): ?Deposit;

    public function referenceExists(TransactionReference $reference): bool;
}
