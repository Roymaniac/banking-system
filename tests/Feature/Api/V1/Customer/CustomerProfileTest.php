<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function customerProfileApiUser(bool $verified = true): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => $verified ? now() : null,
    ]);
}

it('creates a customer profile for the signed-in verified user', function (): void {
    $user = customerProfileApiUser();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/customer/profile', [
        'first_name' => '  Ada ',
        'middle_name' => null,
        'last_name' => ' Lovelace  ',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated()
        ->assertJsonPath('data.customer.first_name', 'Ada')
        ->assertJsonPath('data.customer.middle_name', null)
        ->assertJsonPath('data.customer.last_name', 'Lovelace')
        ->assertJsonPath('data.customer.date_of_birth', '1990-12-10')
        ->assertJsonPath('data.customer.addresses', [])
        ->assertJsonPath('data.customer.contacts', []);

    $this->assertDatabaseHas('customers', [
        'user_id' => $user->identity_user_id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
});

it('returns the signed-in user customer profile', function (): void {
    $user = customerProfileApiUser();
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/customer/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();

    $this->getJson('/api/v1/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.customer.id', $created->json('data.customer.id'))
        ->assertJsonPath('data.customer.first_name', 'Ada');
});

it('does not expose another user customer profile', function (): void {
    $owner = customerProfileApiUser();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/customer/profile', [
        'first_name' => 'Profile',
        'last_name' => 'Owner',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();

    Sanctum::actingAs(customerProfileApiUser());

    $this->getJson('/api/v1/customer/profile')
        ->assertNotFound()
        ->assertJsonPath('message', 'Customer profile not found.');
});

it('prevents a second profile for the same user', function (): void {
    Sanctum::actingAs(customerProfileApiUser());
    $details = [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ];

    $this->postJson('/api/v1/customer/profile', $details)->assertCreated();
    $this->postJson('/api/v1/customer/profile', $details)
        ->assertConflict()
        ->assertJsonPath('message', 'A customer profile already exists for this user.');
});

it('requires a verified user and valid profile details', function (): void {
    Sanctum::actingAs(customerProfileApiUser(false));

    $this->postJson('/api/v1/customer/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ])->assertForbidden();

    Sanctum::actingAs(customerProfileApiUser());

    $this->postJson('/api/v1/customer/profile', [
        'first_name' => '',
        'last_name' => '',
        'date_of_birth' => '2099-01-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth']);
});

it('requires authentication for customer profile endpoints', function (): void {
    $this->getJson('/api/v1/customer/profile')->assertUnauthorized();
    $this->postJson('/api/v1/customer/profile')->assertUnauthorized();
});
