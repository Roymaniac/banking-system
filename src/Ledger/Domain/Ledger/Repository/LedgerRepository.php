<?php

declare(strict_types=1);

namespace Ledger\Domain\Ledger\Repository;

use Account\Domain\Account\ValueObject\AccountId;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/** Defines the storage operations needed by the Ledger domain. */
interface LedgerRepository
{
    public function save(Ledger $ledger): void;

    public function findById(LedgerId $id): ?Ledger;

    public function findByAccountId(AccountId $accountId): ?Ledger;

    public function existsForAccount(AccountId $accountId): bool;
}
