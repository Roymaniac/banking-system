<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Repository;

use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Identity\Domain\User\ValueObject\UserId;

/** Defines the storage operations needed by the Customer domain. */
interface CustomerRepository
{
    public function save(Customer $customer): void;

    public function findById(CustomerId $id): ?Customer;

    public function findByUserId(UserId $userId): ?Customer;

    public function existsForUser(UserId $userId): bool;
}
