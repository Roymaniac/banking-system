<?php

declare(strict_types=1);

use Administration\Application\Department\CreateDepartment;
use Administration\Application\Department\CreateDepartmentCommand;
use Administration\Application\Department\DeactivateDepartment;
use Administration\Application\Department\DeactivateDepartmentCommand;
use Administration\Application\Staff\DeactivateStaff;
use Administration\Application\Staff\HireStaff;
use Administration\Application\Staff\HireStaffCommand;
use Administration\Application\Staff\TransferStaff;
use Administration\Application\Staff\TransferStaffCommand;
use Administration\Domain\Staff\Exception\ActiveDepartmentRequired;
use Administration\Domain\Staff\Exception\StaffAlreadyExists;
use Administration\Domain\Staff\Repository\StaffRepository;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Administration\Infrastructure\Persistence\DatabaseStaffRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('hires, transfers, persists, and deactivates staff', function (): void {
    $operations = app(CreateDepartment::class)->handle(
        new CreateDepartmentCommand(
            'OPS',
            'Operations'
        )
    );

    $risk = app(CreateDepartment::class)->handle(
        new CreateDepartmentCommand(
            'RISK',
            'Risk'
        )
    );

    $staff = app(HireStaff::class)->handle(
        new HireStaffCommand(
            UserId::generate(),
            'emp-100',
            $operations->id(),
            'Analyst'
        )
    );

    app(TransferStaff::class)->handle(
        new TransferStaffCommand(
            $staff->id(),
            $risk->id()
        )
    );

    app(DeactivateStaff::class)->handle($staff->id());

    $stored = app(StaffRepository::class)->findByIdForUpdate($staff->id());

    expect(app(StaffRepository::class))->toBeInstanceOf(DatabaseStaffRepository::class)
        ->and($stored)->not->toBeNull()
        ->and($stored->departmentId()->equals($risk->id()))->toBeTrue()
        ->and($stored->status())->toBe(StaffStatus::Inactive)
        ->and($stored->version())->toBe(3);
});

it('rejects duplicate employee identities and inactive departments', function (): void {
    $department = app(CreateDepartment::class)->handle(
        new CreateDepartmentCommand(
            'OPS',
            'Operations'
        )
    );

    $userId = UserId::generate();
    app(HireStaff::class)->handle(
        new HireStaffCommand(
            $userId,
            'EMP-101',
            $department->id(),
            'Analyst'
        )
    );

    expect(fn () => app(HireStaff::class)->handle(
        new HireStaffCommand(
            $userId,
            'EMP-102',
            $department->id(),
            'Manager'
        )
    ))->toThrow(StaffAlreadyExists::class);

    app(DeactivateDepartment::class)->handle(
        new DeactivateDepartmentCommand(
            $department->id()
        )
    );

    app(HireStaff::class)->handle(
        new HireStaffCommand(
            UserId::generate(),
            'EMP-103',
            $department->id(),
            'Analyst'
        )
    );
})->throws(ActiveDepartmentRequired::class);
