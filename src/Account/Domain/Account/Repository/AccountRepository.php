<?php

declare(strict_types=1);

namespace Account\Domain\Account\Repository;

use Account\Domain\Account\Account;
use Account\Domain\Account\ValueObject\AccountId;
use Customer\Domain\Customer\ValueObject\CustomerId;

/** Defines the storage operations needed by the Account domain. */
interface AccountRepository
{
    public function save(Account $account): void;

    public function findById(AccountId $id): ?Account;

    /** @return list<Account> */
    public function findByCustomerId(CustomerId $customerId): array;
}
