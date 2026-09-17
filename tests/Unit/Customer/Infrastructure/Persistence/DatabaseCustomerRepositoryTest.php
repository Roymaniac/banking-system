<?php

declare(strict_types=1);

use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Customer\Infrastructure\Persistence\DatabaseCustomerRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds the customer repository contract to its database adapter', function (): void {
    expect(app(CustomerRepository::class))->toBeInstanceOf(DatabaseCustomerRepository::class);
});

it('stores and retrieves a customer by both customer and user ID', function (): void {
    $repository = app(DatabaseCustomerRepository::class);
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', 'Byron', 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );

    $repository->save($customer);
    $byId = $repository->findById($customer->id());
    $byUser = $repository->findByUserId($customer->userId());

    expect($byId?->id()->equals($customer->id()))->toBeTrue()
        ->and($byId?->name()->middleName())->toBe('Byron')
        ->and($byId?->dateOfBirth()->value())->toBe('2000-01-01')
        ->and($byId?->recordedEvents())->toBeEmpty()
        ->and($byUser?->id()->equals($customer->id()))->toBeTrue()
        ->and($repository->existsForUser($customer->userId()))->toBeTrue();
});
