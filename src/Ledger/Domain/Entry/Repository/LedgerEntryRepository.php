<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry\Repository;

use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/** Defines storage operations for independently growing ledger entries. */
interface LedgerEntryRepository
{
    public function save(LedgerEntry $entry): void;

    public function findById(LedgerEntryId $id): ?LedgerEntry;

    public function findByReference(LedgerId $ledgerId, EntryReference $reference): ?LedgerEntry;

    public function referenceExists(LedgerId $ledgerId, EntryReference $reference): bool;
}
