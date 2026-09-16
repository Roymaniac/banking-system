<?php

declare(strict_types=1);

use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Infrastructure\Authorization\LaravelGateAuthorizationChecker;
use Tests\TestCase;

uses(TestCase::class);

it('registers the Laravel authorization checker with the application', function (): void {
    expect(app(AuthorizationChecker::class))
        ->toBeInstanceOf(LaravelGateAuthorizationChecker::class);
});
