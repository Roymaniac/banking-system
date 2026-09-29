<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Reporting\Infrastructure\Persistence\DatabaseReportSnapshot;

it('uses PostgreSQL repeatable read before executing report queries', function (): void {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(
            fn(callable $callback): mixed => $callback()
        );

    $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');

    $connection->shouldReceive('statement')
        ->once()
        ->with('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')
        ->andReturnTrue();

    $result = (new DatabaseReportSnapshot($connection))->run(
        fn(): string => 'consistent report'
    );

    expect($result)->toBe('consistent report');
});

it('uses the database transaction snapshot without unsupported SQLite SQL', function (): void {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn(callable $callback): mixed => $callback());

    $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
    $connection->shouldNotReceive('statement');

    expect((new DatabaseReportSnapshot($connection))->run(
        fn(): int => 42
    ))->toBe(42);
});
