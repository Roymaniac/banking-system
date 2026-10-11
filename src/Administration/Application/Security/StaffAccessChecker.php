<?php

declare(strict_types=1);

namespace Administration\Application\Security;

use Identity\Domain\User\ValueObject\UserId;

/** Answers whether an identity still belongs to an active staff member. */
interface StaffAccessChecker
{
    public function isActive(UserId $userId): bool;
}
