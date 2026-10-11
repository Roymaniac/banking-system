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
        $this->deleteSessions($userId, null);
    }

    public function revoke(UserId $userId, int $sessionId): bool
    {
        $authenticationUserId = $this->authenticationUserId($userId);

        if ($authenticationUserId === null) {
            return false;
        }

        return $this->connection->table('personal_access_tokens')
            ->where('id', $sessionId)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $authenticationUserId)
            ->delete() === 1;
    }

    public function revokeAllExcept(UserId $userId, ?int $keptSessionId): void
    {
        $this->deleteSessions($userId, $keptSessionId);
    }

    private function deleteSessions(UserId $userId, ?int $keptSessionId): void
    {
        $authenticationUserId = $this->authenticationUserId($userId);

        if ($authenticationUserId === null) {
            return;
        }

        // A recovered password may mean an old session was stolen, so every
        // device must sign in again with the new password.
        $sessions = $this->connection->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $authenticationUserId);

        if ($keptSessionId !== null) {
            $sessions->where('id', '!=', $keptSessionId);
        }

        $sessions->delete();
    }

    private function authenticationUserId(UserId $userId): ?int
    {
        $id = $this->connection->table('users')
            ->where('identity_user_id', $userId->value())
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
