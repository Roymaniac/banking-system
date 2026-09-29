<?php

declare(strict_types=1);

namespace Reporting\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;

/** Runs every query for one report against the same database snapshot. */
final readonly class DatabaseReportSnapshot
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $report): mixed
    {
        return $this->connection->transaction(function () use ($report): mixed {
            // PostgreSQL otherwise gives each statement a newer committed view.
            if ($this->connection->getDriverName() === 'pgsql') {
                $this->connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $report();
        });
    }
}
