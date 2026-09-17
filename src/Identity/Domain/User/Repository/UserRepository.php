<?php

declare(strict_types=1);

namespace Identity\Domain\User\Repository;

use Identity\Domain\User\User;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\UserId;

/**
 * Describes the storage operations the User domain needs.
 *
 * The interface contains no Laravel or database types, so the domain does not
 * care whether users are stored with Eloquent, SQL, or an in-memory test store.
 */
interface UserRepository
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    public function emailExists(EmailAddress $email): bool;
}
