<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Shared\Contracts\TransactionManager;

/**
 * Uses Laravel's database transaction support for all-or-nothing operations.
 */
final readonly class LaravelTransactionManager implements TransactionManager
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function run(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }
}
