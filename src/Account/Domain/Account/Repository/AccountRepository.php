<?php

declare(strict_types=1);

namespace Account\Domain\Account\Repository;

use Account\Domain\Account\Account;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Customer\Domain\Customer\ValueObject\CustomerId;

/** Defines the storage operations needed by the Account domain. */
interface AccountRepository
{
    public function save(Account $account): void;

    public function findById(AccountId $id): ?Account;

    /** Reads and locks an account until the current database transaction finishes. */
    public function findByIdForUpdate(AccountId $id): ?Account;

    public function findByNumber(AccountNumber $number): ?Account;

    public function numberExists(AccountNumber $number): bool;

    /** @return list<Account> */
    public function findByCustomerId(CustomerId $customerId): array;
}
