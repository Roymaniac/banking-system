<?php

declare(strict_types=1);

namespace Ledger\Domain\Balance\Repository;

use DateTimeImmutable;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/** Stores idempotent balance projections derived from posted entries. */
interface BalanceProjectionRepository
{
    public function apply(LedgerEntry $entry, DateTimeImmutable $projectedAt): void;

    public function find(LedgerId $ledgerId): ?LedgerBalance;

    /** Reads and locks a balance until the current database transaction finishes. */
    public function findForUpdate(LedgerId $ledgerId): ?LedgerBalance;
}
