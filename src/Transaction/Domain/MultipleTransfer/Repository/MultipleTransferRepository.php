<?php

declare(strict_types=1);

namespace Transaction\Domain\MultipleTransfer\Repository;

use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\MultipleTransfer\MultipleTransfer;

/** Defines storage operations for completed multiple transfers. */
interface MultipleTransferRepository
{
    public function save(MultipleTransfer $transfer): void;

    public function findById(TransactionId $id): ?MultipleTransfer;

    public function findByReference(TransactionReference $reference): ?MultipleTransfer;

    public function referenceExists(TransactionReference $reference): bool;
}
