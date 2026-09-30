<?php

declare(strict_types=1);

use Administration\Application\Department\CreateDepartment;
use Administration\Application\Department\CreateDepartmentCommand;
use Administration\Application\Permission\CreatePermission;
use Administration\Application\Permission\GrantPermissionToRole;
use Administration\Application\Permission\RevokePermissionFromRole;
use Administration\Application\Role\AssignRoleToStaff;
use Administration\Application\Role\CreateRole;
use Administration\Application\Role\DeactivateRole;
use Administration\Application\Staff\HireStaff;
use Administration\Application\Staff\HireStaffCommand;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function permissionTestSetup(): array
{
    $department = app(CreateDepartment::class)->handle(new CreateDepartmentCommand('OPS', 'Operations'));
    $userId = UserId::generate();
    $staff = app(HireStaff::class)->handle(new HireStaffCommand($userId, 'EMP-300', $department->id(), 'Approver'));
    $role = app(CreateRole::class)->handle('transfer_approver', 'Transfer Approver');
    app(AssignRoleToStaff::class)->handle($role->id(), $staff->id());
    $permission = app(CreatePermission::class)->handle('transfers.approve', 'Approve transfers');

    return [$userId, $role, $permission];
}

it('authorizes UUID staff through an active role permission', function (): void {
    [$userId, $role, $permission] = permissionTestSetup();
    app(GrantPermissionToRole::class)->handle($role->id(), $permission->id());
    expect(app(AuthorizationChecker::class)->allows($userId, new Permission('transfers.approve')))->toBeTrue()
        ->and(app(AuthorizationChecker::class)->allows($userId, new Permission('accounts.close')))->toBeFalse();
});

it('revokes authorization immediately', function (): void {
    [$userId, $role, $permission] = permissionTestSetup();
    app(GrantPermissionToRole::class)->handle($role->id(), $permission->id());
    app(RevokePermissionFromRole::class)->handle($role->id(), $permission->id());
    expect(app(AuthorizationChecker::class)->allows($userId, new Permission('transfers.approve')))->toBeFalse();
});

it('ignores permissions supplied by an inactive role', function (): void {
    [$userId, $role, $permission] = permissionTestSetup();
    app(GrantPermissionToRole::class)->handle($role->id(), $permission->id());
    app(DeactivateRole::class)->handle($role->id());
    expect(app(AuthorizationChecker::class)->allows($userId, new Permission('transfers.approve')))->toBeFalse();
});
