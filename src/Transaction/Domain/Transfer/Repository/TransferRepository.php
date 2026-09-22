<?php

declare(strict_types=1);

namespace Transaction\Domain\Transfer\Repository;

use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Transfer\Transfer;

/** Defines storage operations for completed account-to-account transfers. */
interface TransferRepository
{
    public function save(Transfer $transfer): void;

    public function findById(TransactionId $id): ?Transfer;

    public function findByReference(TransactionReference $reference): ?Transfer;

    public function referenceExists(TransactionReference $reference): bool;
}
