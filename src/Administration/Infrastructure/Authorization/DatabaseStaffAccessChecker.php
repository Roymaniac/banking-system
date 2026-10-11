<?php

declare(strict_types=1);

namespace Administration\Infrastructure\Authorization;

use Administration\Application\Security\StaffAccessChecker;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/** Checks current staff status directly so deactivation takes effect immediately. */
final readonly class DatabaseStaffAccessChecker implements StaffAccessChecker
{
    public function __construct(private ConnectionInterface $connection) {}

    public function isActive(UserId $userId): bool
    {
        return $this->connection->table('staff')
            ->where('user_id', $userId->value())
            ->where('status', StaffStatus::Active->value)
            ->exists();
    }
}
