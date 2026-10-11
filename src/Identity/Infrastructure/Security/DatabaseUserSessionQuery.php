<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Security;

use App\Models\User;
use DateTimeImmutable;
use Identity\Application\Session\UserSessionQuery;
use Identity\Application\Session\View\UserSessionPage;
use Identity\Application\Session\View\UserSessionView;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;
use stdClass;

/** Reads safe session metadata from Laravel Sanctum's token table. */
final readonly class DatabaseUserSessionQuery implements UserSessionQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function paginate(
        UserId $userId,
        int $currentSessionId,
        int $page,
        int $perPage,
    ): UserSessionPage {
        $authenticationUserId = $this->authenticationUserId($userId);

        if ($authenticationUserId === null) {
            return new UserSessionPage([], $page, $perPage, 0);
        }

        $sessions = $this->connection->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $authenticationUserId);

        $total = (clone $sessions)->count();
        $records = $sessions
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return new UserSessionPage(
            sessions: $records->map(
                fn (stdClass $record): UserSessionView => $this->toView($record, $currentSessionId),
            )->all(),
            page: $page,
            perPage: $perPage,
            total: $total,
        );
    }

    private function authenticationUserId(UserId $userId): ?int
    {
        $id = $this->connection->table('users')
            ->where('identity_user_id', $userId->value())
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function toView(stdClass $record, int $currentSessionId): UserSessionView
    {
        $abilities = json_decode((string) $record->abilities, true);

        return new UserSessionView(
            id: (int) $record->id,
            deviceName: (string) $record->name,
            abilities: is_array($abilities)
                ? array_values(array_filter($abilities, 'is_string'))
                : [],
            isCurrent: (int) $record->id === $currentSessionId,
            lastUsedAt: $record->last_used_at === null
                ? null
                : new DateTimeImmutable((string) $record->last_used_at),
            expiresAt: $record->expires_at === null
                ? null
                : new DateTimeImmutable((string) $record->expires_at),
            createdAt: new DateTimeImmutable((string) $record->created_at),
        );
    }
}
