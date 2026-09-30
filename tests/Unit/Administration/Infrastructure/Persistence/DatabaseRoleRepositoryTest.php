<?php

declare(strict_types=1);

use Administration\Application\Department\CreateDepartment;
use Administration\Application\Department\CreateDepartmentCommand;
use Administration\Application\Role\AssignRoleToStaff;
use Administration\Application\Role\CreateRole;
use Administration\Application\Role\DeactivateRole;
use Administration\Application\Staff\HireStaff;
use Administration\Application\Staff\HireStaffCommand;
use Administration\Domain\Role\Exception\InactiveRoleCannotChange;
use Administration\Domain\Role\Exception\RoleAlreadyAssigned;
use Administration\Domain\Role\Exception\RoleNameAlreadyExists;
use Administration\Domain\Role\Repository\RoleAssignmentRepository;
use Administration\Domain\Role\Repository\RoleRepository;
use Administration\Domain\Role\ValueObject\RoleStatus;
use Administration\Domain\Staff\Staff;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function roleTestStaff(): Staff
{
    $department = app(CreateDepartment::class)->handle(
        new CreateDepartmentCommand(
            'OPS',
            'Operations'
        )
    );

    return app(HireStaff::class)->handle(
        new HireStaffCommand(
            UserId::generate(),
            'EMP-200',
            $department->id(),
            'Operations Analyst'
        )
    );
}

it('creates and assigns a role to active staff', function (): void {
    $staff = roleTestStaff();
    $role = app(CreateRole::class)->handle(
        'transaction_approver',
        'Transaction Approver'
    );

    app(AssignRoleToStaff::class)->handle($role->id(), $staff->id());

    $stored = app(RoleRepository::class)->findByIdForUpdate($role->id());

    expect($stored)->not->toBeNull()
        ->and($stored->name()->value)->toBe('transaction_approver')
        ->and($stored->version())->toBe(2)
        ->and(app(RoleAssignmentRepository::class)->exists($role->id(), $staff->id()))->toBeTrue()
        ->and(DB::table('staff_role_assignments')->count())->toBe(1);
});

it('rejects duplicate role names and assignments', function (): void {
    $staff = roleTestStaff();

    $role = app(CreateRole::class)->handle('auditor', 'Auditor');

    expect(fn() => app(CreateRole::class)->handle(' AUDITOR ', 'Other'))
        ->toThrow(RoleNameAlreadyExists::class);

    app(AssignRoleToStaff::class)->handle($role->id(), $staff->id());
    app(AssignRoleToStaff::class)->handle($role->id(), $staff->id());
})->throws(RoleAlreadyAssigned::class);

it('prevents assigning a deactivated role', function (): void {
    $staff = roleTestStaff();

    $role = app(CreateRole::class)->handle('viewer', 'Read Only Viewer');

    app(DeactivateRole::class)->handle($role->id());

    $stored = app(RoleRepository::class)->findByIdForUpdate($role->id());

    expect($stored->status())->toBe(RoleStatus::Inactive);
    app(AssignRoleToStaff::class)->handle($role->id(), $staff->id());
})->throws(InactiveRoleCannotChange::class);
