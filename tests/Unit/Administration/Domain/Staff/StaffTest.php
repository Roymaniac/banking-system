<?php

declare(strict_types=1);

use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\Event\StaffDeactivated;
use Administration\Domain\Staff\Event\StaffHired;
use Administration\Domain\Staff\Event\StaffTransferred;
use Administration\Domain\Staff\Exception\InactiveStaffCannotChange;
use Administration\Domain\Staff\Staff;
use Administration\Domain\Staff\ValueObject\EmployeeNumber;
use Administration\Domain\Staff\ValueObject\JobTitle;
use Administration\Domain\Staff\ValueObject\StaffId;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;

it('hires, transfers, and deactivates a staff member with versioned events', function (): void {
    $staff = Staff::hire(
        StaffId::generate(),
        UserId::generate(),
        new EmployeeNumber('emp-001'),
        $first = DepartmentId::generate(),
        new JobTitle('Operations Analyst'),
        new DateTimeImmutable,
        Uuid::generate()
    );

    expect($staff->employeeNumber()->value)->toBe('EMP-001')
        ->and($staff->pullDomainEvents()[0])
        ->toBeInstanceOf(StaffHired::class);

    $staff->transfer(
        $second = DepartmentId::generate(),
        new DateTimeImmutable,
        Uuid::generate()
    );

    $staff->deactivate(
        new DateTimeImmutable,
        Uuid::generate()
    );

    $events = $staff->pullDomainEvents();
    expect($staff->departmentId()->equals($second))->toBeTrue()
        ->and($staff->status())->toBe(StaffStatus::Inactive)
        ->and($staff->version())->toBe(3)
        ->and($events[0])->toBeInstanceOf(StaffTransferred::class)
        ->and($events[1])->toBeInstanceOf(StaffDeactivated::class);
});

it('prevents transferring inactive staff', function (): void {
    $staff = Staff::reconstitute(
        StaffId::generate(),
        UserId::generate(),
        new EmployeeNumber('EMP-002'),
        DepartmentId::generate(),
        new JobTitle('Teller'),
        new DateTimeImmutable,
        StaffStatus::Inactive,
        new DateTimeImmutable,
        2
    );

    $staff->transfer(
        DepartmentId::generate(),
        new DateTimeImmutable,
        Uuid::generate()
    );
})->throws(InactiveStaffCannotChange::class);
