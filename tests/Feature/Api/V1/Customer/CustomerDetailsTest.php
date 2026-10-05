<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function customerDetailsUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function createCustomerDetailsProfile(): void
{
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
}

function customerAddressPayload(array $changes = []): array
{
    return array_merge([
        'type' => 'residential',
        'line_one' => '12 Marina Road',
        'line_two' => null,
        'city' => 'Lagos',
        'state_or_region' => 'Lagos',
        'postal_code' => '100001',
        'country_code' => 'NG',
    ], $changes);
}

it('adds an address and includes it in the customer profile', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $created = $this->postJson('/api/v1/customer/profile/addresses', customerAddressPayload())
        ->assertCreated();

    $this->getJson('/api/v1/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.customer.addresses.0.id', $created->json('data.address_id'))
        ->assertJsonPath('data.customer.addresses.0.type', 'residential')
        ->assertJsonPath('data.customer.addresses.0.country_code', 'NG');
});

it('replaces an existing address', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $addressId = $this->postJson(
        '/api/v1/customer/profile/addresses',
        customerAddressPayload(),
    )->json('data.address_id');

    $this->putJson(
        "/api/v1/customer/profile/addresses/{$addressId}",
        customerAddressPayload(['type' => 'mailing', 'city' => 'Abuja']),
    )->assertOk()
        ->assertJsonPath('message', 'Customer address updated successfully.');

    $this->getJson('/api/v1/customer/profile')
        ->assertJsonPath('data.customer.addresses.0.type', 'mailing')
        ->assertJsonPath('data.customer.addresses.0.city', 'Abuja');
});

it('rejects duplicate and invalid addresses', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $this->postJson('/api/v1/customer/profile/addresses', customerAddressPayload())->assertCreated();
    $this->postJson('/api/v1/customer/profile/addresses', customerAddressPayload())
        ->assertConflict()
        ->assertJsonPath('message', 'This address is already registered for the customer.');

    $this->postJson('/api/v1/customer/profile/addresses', customerAddressPayload([
        'type' => 'office',
        'country_code' => 'Nigeria',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'country_code']);
});

it('adds and updates a contact method', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $contactId = $this->postJson('/api/v1/customer/profile/contacts', [
        'type' => 'phone',
        'value' => '+234 801 234 5678',
    ])->assertCreated()
        ->json('data.contact_id');

    $this->putJson("/api/v1/customer/profile/contacts/{$contactId}", [
        'type' => 'email',
        'value' => 'ADA@EXAMPLE.COM',
    ])->assertOk()
        ->assertJsonPath('message', 'Customer contact updated successfully.');

    $this->getJson('/api/v1/customer/profile')
        ->assertJsonPath('data.customer.contacts.0.type', 'email')
        ->assertJsonPath('data.customer.contacts.0.value', 'ada@example.com');
});

it('validates contact values and prevents duplicates', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $contact = ['type' => 'email', 'value' => 'ada@example.com'];

    $this->postJson('/api/v1/customer/profile/contacts', $contact)->assertCreated();
    $this->postJson('/api/v1/customer/profile/contacts', $contact)
        ->assertConflict()
        ->assertJsonPath('message', 'This contact is already registered for the customer.');

    $this->postJson('/api/v1/customer/profile/contacts', [
        'type' => 'phone',
        'value' => '08012345678',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

it('cannot update details owned by another customer', function (): void {
    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $addressId = $this->postJson(
        '/api/v1/customer/profile/addresses',
        customerAddressPayload(),
    )->json('data.address_id');

    Sanctum::actingAs(customerDetailsUser());
    createCustomerDetailsProfile();

    $this->putJson(
        "/api/v1/customer/profile/addresses/{$addressId}",
        customerAddressPayload(['city' => 'Abuja']),
    )->assertNotFound()
        ->assertJsonPath('message', 'The requested customer address does not exist.');
});

it('requires both authentication and an existing customer profile', function (): void {
    $this->postJson('/api/v1/customer/profile/addresses', customerAddressPayload())
        ->assertUnauthorized();

    Sanctum::actingAs(customerDetailsUser());

    $this->postJson('/api/v1/customer/profile/contacts', [
        'type' => 'email',
        'value' => 'ada@example.com',
    ])->assertNotFound()
        ->assertJsonPath('message', 'Customer profile not found.');
});
