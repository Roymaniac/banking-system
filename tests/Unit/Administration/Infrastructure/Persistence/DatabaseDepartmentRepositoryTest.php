<?php

declare(strict_types=1);

use Administration\Application\Department\CreateDepartment;
use Administration\Application\Department\CreateDepartmentCommand;
use Administration\Application\Department\DeactivateDepartment;
use Administration\Application\Department\DeactivateDepartmentCommand;
use Administration\Application\Department\RenameDepartment;
use Administration\Application\Department\RenameDepartmentCommand;
use Administration\Domain\Department\Department;
use Administration\Domain\Department\Exception\DepartmentCodeAlreadyExists;
use Administration\Domain\Department\Repository\DepartmentRepository;
use Administration\Domain\Department\ValueObject\DepartmentCode;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Department\ValueObject\DepartmentName;
use Administration\Domain\Department\ValueObject\DepartmentStatus;
use Administration\Infrastructure\Persistence\DatabaseDepartmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds and round-trips departments through the database repository', function (): void {
    $repository = app(DepartmentRepository::class);
    $department = Department::create(
        DepartmentId::generate(),
        new DepartmentCode('FIN'),
        new DepartmentName('Finance'),
        new DateTimeImmutable('2026-09-29T09:00:00+01:00'),
        Uuid::generate()
    );
    $repository->save($department);
    $department->pullDomainEvents();
    $stored = $repository->findById($department->id());
    expect($repository)->toBeInstanceOf(DatabaseDepartmentRepository::class)
        ->and($stored)->not->toBeNull()
        ->and($stored->code()->value)->toBe('FIN')
        ->and($stored->name()->value)->toBe('Finance')
        ->and($stored->version())->toBe(1)
        ->and($repository->codeExists(new DepartmentCode('fin')))->toBeTrue();
});

it('creates, renames, and deactivates a department through its application services', function (): void {
    $department = app(CreateDepartment::class)->handle(new CreateDepartmentCommand('ops', 'Operations'));
    app(RenameDepartment::class)->handle(new RenameDepartmentCommand($department->id(), 'Banking Operations'));
    app(DeactivateDepartment::class)->handle(new DeactivateDepartmentCommand($department->id()));

    $stored = app(DepartmentRepository::class)->findById($department->id());

    expect($stored)->not->toBeNull()
        ->and($stored->name()->value)->toBe('Banking Operations')
        ->and($stored->status())->toBe(DepartmentStatus::Inactive)
        ->and($stored->version())->toBe(3);
});

it('rejects a duplicate normalized department code', function (): void {
    app(CreateDepartment::class)->handle(new CreateDepartmentCommand('risk', 'Risk'));
    app(CreateDepartment::class)->handle(new CreateDepartmentCommand(' RISK ', 'Other Risk'));
})->throws(DepartmentCodeAlreadyExists::class, 'A department with code RISK already exists.');
