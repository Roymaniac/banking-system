<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function accountApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function createAccountApiProfile(): void
{
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
}

function requestAccount(array $changes = []): TestResponse
{
    return test()->postJson('/api/v1/accounts', array_merge([
        'type' => 'savings',
        'currency' => 'ngn',
    ], $changes));
}

it('creates a pending account for the signed-in customer', function (): void {
    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    requestAccount()->assertCreated()
        ->assertJsonPath('data.account.type', 'savings')
        ->assertJsonPath('data.account.currency', 'NGN')
        ->assertJsonPath('data.account.status', 'pending')
        ->assertJsonPath('data.account.number', null);

    $this->assertDatabaseHas('accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
        'status' => 'pending',
    ]);
});

it('lists only the signed-in customer accounts', function (): void {
    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    $ownAccountId = requestAccount()->assertCreated()->json('data.account.id');

    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    requestAccount(['type' => 'current', 'currency' => 'USD'])->assertCreated();

    $response = $this->getJson('/api/v1/accounts')->assertOk();

    expect($response->json('data.accounts'))->toHaveCount(1);
    $response->assertJsonPath('data.accounts.0.type', 'current')
        ->assertJsonMissing(['id' => $ownAccountId]);
});

it('returns an account owned by the signed-in customer', function (): void {
    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    $accountId = requestAccount()->assertCreated()->json('data.account.id');

    $this->getJson("/api/v1/accounts/{$accountId}")
        ->assertOk()
        ->assertJsonPath('data.account.id', $accountId)
        ->assertJsonPath('data.account.status', 'pending')
        ->assertJsonMissingPath('data.account.freeze_reason')
        ->assertJsonMissingPath('data.account.closure_reason');
});

it('hides accounts belonging to another customer', function (): void {
    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    $foreignAccountId = requestAccount()->assertCreated()->json('data.account.id');

    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    $this->getJson("/api/v1/accounts/{$foreignAccountId}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');
});

it('validates the requested account type and currency', function (): void {
    Sanctum::actingAs(accountApiUser());
    createAccountApiProfile();

    requestAccount(['type' => 'investment', 'currency' => 'NAIRA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'currency']);
});

it('requires authentication and a customer profile', function (): void {
    $this->getJson('/api/v1/accounts')->assertUnauthorized();
    $this->postJson('/api/v1/accounts')->assertUnauthorized();

    Sanctum::actingAs(accountApiUser());

    $this->getJson('/api/v1/accounts')
        ->assertNotFound()
        ->assertJsonPath('message', 'Customer profile not found.');

    requestAccount()->assertNotFound();
});
