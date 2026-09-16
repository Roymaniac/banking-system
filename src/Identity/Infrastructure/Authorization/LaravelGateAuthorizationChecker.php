<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Authorization;

use App\Models\User as LaravelUser;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Adapts Laravel's Gate system to the Identity authorization boundary.
 *
 * Laravel Gate can later receive permissions from Spatie without changing any
 * application or domain code.
 */
final readonly class LaravelGateAuthorizationChecker implements AuthorizationChecker
{
    public function __construct(
        private Gate $gate,
        private LaravelUser $users,
    ) {}

    public function allows(UserId $userId, Permission $permission): bool
    {
        $user = $this->users->newQuery()->find($userId->value());

        if (! $user instanceof Authenticatable) {
            return false;
        }

        return $this->gate
            ->forUser($user)
            ->allows($permission->value());
    }
}
