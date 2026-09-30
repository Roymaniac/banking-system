<?php

declare(strict_types=1);

use Administration\Infrastructure\Authorization\DatabaseAuthorizationChecker;
use Identity\Application\Authorization\AuthorizationChecker;
use Tests\TestCase;

uses(TestCase::class);

it('registers the UUID-aware authorization checker with the application', function (): void {
    expect(app(AuthorizationChecker::class))
        ->toBeInstanceOf(DatabaseAuthorizationChecker::class);
});
