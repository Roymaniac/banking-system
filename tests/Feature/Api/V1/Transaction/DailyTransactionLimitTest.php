<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;

uses(RefreshDatabase::class);

function dailyLimitApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function createDailyLimitApiAccount(User $user): string
{
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Limit',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();

    return test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');
}

function configureDailyLimitApiAccount(string $accountId, int $maximumMinorUnits = 100000): void
{
    $now = app(Clock::class)->now();
    app(DailyTransactionLimitRepository::class)->save(DailyTransactionLimit::configure(
        new AccountId($accountId),
        new LedgerCurrency('NGN'),
        $maximumMinorUnits,
        $now,
        Uuid::generate(),
    ));
}

it('shows the bank maximum and today available allowance', function (): void {
    $user = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($user);
    configureDailyLimitApiAccount($accountId);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/accounts/{$accountId}/daily-limit")
        ->assertOk()
        ->assertJsonPath('data.daily_limit.currency', 'NGN')
        ->assertJsonPath('data.daily_limit.bank_maximum_minor_units', 100000)
        ->assertJsonPath('data.daily_limit.customer_maximum_minor_units', null)
        ->assertJsonPath('data.daily_limit.effective_maximum_minor_units', 100000)
        ->assertJsonPath('data.daily_limit.used_minor_units', 0)
        ->assertJsonPath('data.daily_limit.remaining_minor_units', 100000);
});

it('allows a customer to lower the effective limit while preserving the bank maximum', function (): void {
    $user = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($user);
    configureDailyLimitApiAccount($accountId);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 40000,
    ])->assertOk()
        ->assertJsonPath('data.daily_limit.bank_maximum_minor_units', 100000)
        ->assertJsonPath('data.daily_limit.customer_maximum_minor_units', 40000)
        ->assertJsonPath('data.daily_limit.effective_maximum_minor_units', 40000);

    $this->assertDatabaseHas('daily_transaction_limits', [
        'account_id' => $accountId,
        'maximum_minor_units' => 100000,
        'customer_maximum_minor_units' => 40000,
    ]);
});

it('does not let a customer raise a previously reduced limit', function (): void {
    $user = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($user);
    configureDailyLimitApiAccount($accountId);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 40000,
    ])->assertOk();
    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 50000,
    ])->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'A customer may only reduce the current daily transaction limit.',
        );
});

it('keeps spending already used today when the limit is reduced', function (): void {
    $user = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($user);
    configureDailyLimitApiAccount($accountId);
    $now = app(Clock::class)->now();
    DB::table('daily_transaction_limit_usages')->insert([
        'account_id' => $accountId,
        'usage_date' => $now->format('Y-m-d'),
        'currency' => 'NGN',
        'used_minor_units' => 30000,
        'updated_at' => $now,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 25000,
    ])->assertOk()
        ->assertJsonPath('data.daily_limit.used_minor_units', 30000)
        ->assertJsonPath('data.daily_limit.remaining_minor_units', 0);
});

it('reports an unconfigured limit and refuses customer configuration', function (): void {
    $user = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($user);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/accounts/{$accountId}/daily-limit")
        ->assertOk()
        ->assertJsonPath('data.daily_limit', null);
    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 10000,
    ])->assertConflict()
        ->assertJsonPath(
            'message',
            'The bank has not configured a daily transaction limit for this account.',
        );
});

it('enforces ownership, authentication, and positive input', function (): void {
    $owner = dailyLimitApiUser();
    $accountId = createDailyLimitApiAccount($owner);
    configureDailyLimitApiAccount($accountId);

    Sanctum::actingAs(dailyLimitApiUser());
    $this->getJson("/api/v1/accounts/{$accountId}/daily-limit")
        ->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');

    Sanctum::actingAs($owner);
    $this->patchJson("/api/v1/accounts/{$accountId}/daily-limit", [
        'maximum_minor_units' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['maximum_minor_units']);

    app('auth')->forgetGuards();
    $this->getJson('/api/v1/accounts/'.AccountId::generate()->value().'/daily-limit')
        ->assertUnauthorized();
});
