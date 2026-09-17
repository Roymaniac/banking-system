<?php

declare(strict_types=1);

use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Event\CustomerProfileCreated;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;

it('creates a profile and records a privacy-safe domain event', function (): void {
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::create(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        Uuid::generate(),
    );

    $event = $customer->recordedEvents()[0];

    expect($customer->version())->toBe(1)
        ->and($event)->toBeInstanceOf(CustomerProfileCreated::class)
        ->and($event->payload())->toBe(['user_id' => $customer->userId()->value()])
        ->and($event->payload())->not->toHaveKeys(['first_name', 'last_name', 'date_of_birth']);
});

it('rebuilds a stored profile without recording a new event', function (): void {
    $now = new DateTimeImmutable('2026-09-17T10:00:00+01:00');
    $customer = Customer::reconstitute(
        CustomerId::generate(),
        UserId::generate(),
        new PersonalName('Ada', null, 'Lovelace'),
        DateOfBirth::fromString('2000-01-01', $now),
        $now,
        4,
    );

    expect($customer->version())->toBe(4)
        ->and($customer->recordedEvents())->toBeEmpty();
});
