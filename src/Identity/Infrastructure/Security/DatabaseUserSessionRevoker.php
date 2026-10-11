<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use App\Models\User;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/** Revokes Laravel Sanctum tokens through their linked Identity user. */
final readonly class DatabaseUserSessionRevoker implements UserSessionRevoker
{
    public function __construct(private ConnectionInterface $connection) {}

    public function revokeAll(UserId $userId): void
    {
        $authenticationUserId = $this->connection->table('users')
            ->where('identity_user_id', $userId->value())
            ->value('id');

        if ($authenticationUserId === null) {
            return;
        }

        // A recovered password may mean an old session was stolen, so every
        // device must sign in again with the new password.
        $this->connection->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $authenticationUserId)
            ->delete();
    }
}
