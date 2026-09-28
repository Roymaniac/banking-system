<?php

declare(strict_types=1);

use Customer\Domain\Customer\ValueObject\CustomerId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Reporting\Application\Customer\CustomerReportQuery;
use Reporting\Infrastructure\Persistence\DatabaseCustomerReportQuery;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('binds customer reporting to its database query', function (): void {
    expect(app(CustomerReportQuery::class))
        ->toBeInstanceOf(DatabaseCustomerReportQuery::class);
});

it('assembles a customer report without duplicating related records', function (): void {
    $customerId = CustomerId::generate();
    DB::table('customers')->insert([
        'id' => $customerId->value(),
        'user_id' => Uuid::generate()->value(),
        'first_name' => 'Ada',
        'middle_name' => 'Nneka',
        'last_name' => 'Okafor',
        'date_of_birth' => '1990-01-02',
        'registered_at' => '2026-09-27 08:00:00',
        'version' => 1,
    ]);
    DB::table('customer_addresses')->insert([
        'id' => Uuid::generate()->value(),
        'customer_id' => $customerId->value(),
        'type' => 'home',
        'line_one' => '10 Marina Road',
        'line_two' => null,
        'city' => 'Lagos',
        'state_or_region' => 'Lagos',
        'postal_code' => '100001',
        'country_code' => 'NG',
    ]);
    DB::table('customer_contacts')->insert([
        'id' => Uuid::generate()->value(),
        'customer_id' => $customerId->value(),
        'type' => 'phone',
        'value' => '+2348012345678',
    ]);
    DB::table('accounts')->insert([
        [
            'id' => Uuid::generate()->value(),
            'customer_id' => $customerId->value(),
            'number' => '1000000001',
            'type' => 'savings',
            'currency' => 'NGN',
            'status' => 'active',
            'created_at' => '2026-09-27 09:00:00',
            'version' => 1,
        ],
        [
            'id' => Uuid::generate()->value(),
            'customer_id' => $customerId->value(),
            'number' => null,
            'type' => 'current',
            'currency' => 'USD',
            'status' => 'pending',
            'created_at' => '2026-09-27 10:00:00',
            'version' => 1,
        ],
    ]);

    $report = app(CustomerReportQuery::class)->find($customerId);

    expect($report)->not->toBeNull()
        ->and($report->fullName())->toBe('Ada Nneka Okafor')
        ->and($report->addresses)->toHaveCount(1)
        ->and($report->addresses[0]->city)->toBe('Lagos')
        ->and($report->contacts)->toHaveCount(1)
        ->and($report->contacts[0]->value)->toBe('+2348012345678')
        ->and($report->accounts)->toHaveCount(2)
        ->and($report->accounts[0]->number)->toBe('1000000001')
        ->and($report->accounts[1]->status)->toBe('pending')
        ->and($report->accountCount())->toBe(2);
});

it('returns null when no customer matches the requested identifier', function (): void {
    expect(app(CustomerReportQuery::class)
        ->find(CustomerId::generate()))
        ->toBeNull();
});
