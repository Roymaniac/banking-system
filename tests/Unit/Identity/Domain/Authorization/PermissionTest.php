<?php

declare(strict_types=1);

use Identity\Domain\Authorization\ValueObject\Permission;

it('normalizes a readable permission name', function (): void {
    $permission = new Permission('  Accounts.VIEW  ');

    expect($permission->value())->toBe('accounts.view');
});

it('rejects permission names that do not use dot notation', function (): void {
    new Permission('view accounts');
})->throws(
    InvalidArgumentException::class,
    'A permission must use dot notation, for example "accounts.view".',
);
