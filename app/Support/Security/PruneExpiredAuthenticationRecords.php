<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;

/** Removes authentication records that can no longer be used safely. */
final readonly class PruneExpiredAuthenticationRecords
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    /** @return array{mfa_challenges: int, access_tokens: int} */
    public function prune(): array
    {
        return $this->connection->transaction(function (): array {
            $now = $this->clock->now();
            $challenges = $this->connection->table('multi_factor_login_challenges')
                ->where('expires_at', '<=', $now)
                ->delete();

            $tokens = $this->connection->table('personal_access_tokens')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->delete();

            return [
                'mfa_challenges' => $challenges,
                'access_tokens' => $tokens,
            ];
        });
    }
}
