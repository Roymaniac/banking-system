<?php

declare(strict_types=1);

use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Infrastructure\Event\LaravelEventPublisher;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Tests\TestCase;

uses(TestCase::class);

it('registers the shared support services with Laravel', function (): void {
    expect(app(EventPublisher::class))->toBeInstanceOf(LaravelEventPublisher::class)
        ->and(app(TransactionManager::class))->toBeInstanceOf(LaravelTransactionManager::class);
});
