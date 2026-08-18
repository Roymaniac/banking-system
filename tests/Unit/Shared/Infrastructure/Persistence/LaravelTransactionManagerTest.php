<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;

it('returns the result of work run inside a database transaction', function (): void {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(static fn (callable $callback): mixed => $callback());

    $transactionManager = new LaravelTransactionManager($connection);

    expect($transactionManager->run(static fn (): string => 'transfer-complete'))
        ->toBe('transfer-complete');
});
