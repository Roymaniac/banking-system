<?php

declare(strict_types=1);

use Administration\Domain\Department\Department;
use Administration\Domain\Department\Event\DepartmentCreated;
use Administration\Domain\Department\Event\DepartmentDeactivated;
use Administration\Domain\Department\Event\DepartmentRenamed;
use Administration\Domain\Department\Exception\InactiveDepartmentCannotChange;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use Shared\Domain\Identifier\Uuid;

it('creates an active department with normalized values', function (): void {
    $department = Department::create(
        DepartmentId::generate(),
        new DepartmentCode(' risk_ops '),
        new DepartmentName(' Risk   Operations '),
        new DateTimeImmutable('2026-09-29T09:00:00+01:00'),
        Uuid::generate()
    );

    expect($department->code()->value)->toBe('RISK_OPS')
        ->and($department->name()->value)->toBe('Risk Operations')
        ->and($department->status())->toBe(DepartmentStatus::Active)
        ->and($department->version())->toBe(1)
        ->and($department->pullDomainEvents()[0])->toBeInstanceOf(DepartmentCreated::class);
});

it('renames and deactivates an active department with versioned events', function (): void {
    $department = Department::reconstitute(
        DepartmentId::generate(),
        new DepartmentCode('OPS'),
        new DepartmentName('Operations'),
        new DateTimeImmutable,
        DepartmentStatus::Active,
        null,
        1
    );

    $department->rename(
        new DepartmentName('Banking Operations'),
        new DateTimeImmutable,
        Uuid::generate()
    );

    $department->deactivate(new DateTimeImmutable, Uuid::generate());
    $events = $department->pullDomainEvents();

    expect($department->name()->value)->toBe('Banking Operations')
        ->and($department->status())->toBe(DepartmentStatus::Inactive)
        ->and($department->version())->toBe(3)
        ->and($events[0])->toBeInstanceOf(DepartmentRenamed::class)
        ->and($events[1])->toBeInstanceOf(DepartmentDeactivated::class);
});

it('prevents changes to an inactive department', function (): void {
    $department = Department::reconstitute(
        DepartmentId::generate(),
        new DepartmentCode('OPS'),
        new DepartmentName('Operations'),
        new DateTimeImmutable,
        DepartmentStatus::Inactive,
        new DateTimeImmutable,
        2
    );

    $department->rename(
        new DepartmentName('Changed'),
        new DateTimeImmutable,
        Uuid::generate()
    );
})->throws(InactiveDepartmentCannotChange::class);
