<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\Clock;

/** Removes expired retry responses after clients have had time to recover them. */
final readonly class PruneExpiredIdempotencyRecords
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function prune(): int
    {
        return $this->connection->table('api_idempotency_records')
            ->where('expires_at', '<=', $this->clock->now())
            ->delete();
    }
}
