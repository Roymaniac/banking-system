<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\Repository;

use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\Reversal;

/** Defines storage operations and duplicate checks for transaction reversals. */
interface ReversalRepository
{
    public function save(Reversal $reversal): void;

    public function findById(TransactionId $id): ?Reversal;

    public function findByReference(TransactionReference $reference): ?Reversal;

    public function referenceExists(TransactionReference $reference): bool;

    public function originalEntryWasReversed(LedgerEntryId $entryId): bool;
}
