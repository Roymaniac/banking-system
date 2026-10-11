<?php

declare(strict_types=1);

use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function accountLifecycleUser(string $email): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => $email,
        'email_verified_at' => now(),
    ]);
}

/** @return array{account_id: string, ledger_id: string} */
function createLifecycleAccount(): array
{
    Sanctum::actingAs(accountLifecycleUser('lifecycle-customer@example.com'));
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Lifecycle',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'current',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');
    $ledgerId = Uuid::generate()->value();

    DB::table('accounts')->where('id', $accountId)->update([
        'number' => '1945678901',
        'status' => 'active',
        'version' => 3,
    ]);
    DB::table('ledgers')->insert([
        'id' => $ledgerId,
        'account_id' => $accountId,
        'currency' => 'NGN',
        'created_at' => now(),
        'version' => 1,
    ]);

    return ['account_id' => $accountId, 'ledger_id' => $ledgerId];
}

function allowAccountLifecycle(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return in_array($permission->value(), ['accounts.freeze', 'accounts.close'], true);
        }
    });
}

it('freezes and unfreezes an account with an auditable controlled reason', function (): void {
    $record = createLifecycleAccount();
    allowAccountLifecycle();
    Sanctum::actingAs(accountLifecycleUser('lifecycle-operator@example.com'), ['privileged']);
    $requestId = Uuid::generate()->value();

    $this->withHeader('X-Request-ID', $requestId)
        ->postJson("/api/v1/operations/accounts/{$record['account_id']}/freeze", [
            'reason' => 'compliance_review',
        ])->assertOk()
        ->assertJsonPath('message', 'Account frozen successfully.');
    $this->assertDatabaseHas('accounts', [
        'id' => $record['account_id'],
        'status' => 'frozen',
        'freeze_reason' => 'compliance_review',
    ]);
    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'account.frozen',
        'aggregate_id' => $record['account_id'],
        'correlation_id' => $requestId,
    ]);

    $this->postJson("/api/v1/operations/accounts/{$record['account_id']}/unfreeze")
        ->assertOk()
        ->assertJsonPath('message', 'Account unfrozen successfully.');
    $this->assertDatabaseHas('accounts', [
        'id' => $record['account_id'],
        'status' => 'active',
        'freeze_reason' => null,
        'frozen_at' => null,
    ]);
});

it('permanently closes a zero-balance active or frozen account', function (string $startingStatus): void {
    $record = createLifecycleAccount();
    DB::table('accounts')->where('id', $record['account_id'])->update([
        'status' => $startingStatus,
        'freeze_reason' => $startingStatus === 'frozen' ? 'customer_request' : null,
        'frozen_at' => $startingStatus === 'frozen' ? now() : null,
    ]);
    allowAccountLifecycle();
    Sanctum::actingAs(accountLifecycleUser("close-{$startingStatus}-operator@example.com"), ['privileged']);

    $this->postJson("/api/v1/operations/accounts/{$record['account_id']}/close", [
        'reason' => 'customer_request',
    ])->assertOk()
        ->assertJsonPath('message', 'Account closed successfully.');

    $this->assertDatabaseHas('accounts', [
        'id' => $record['account_id'],
        'status' => 'closed',
        'closure_reason' => 'customer_request',
        'freeze_reason' => null,
    ]);
})->with(['active', 'frozen']);

it('refuses to close an account that still holds money', function (): void {
    $record = createLifecycleAccount();
    DB::table('ledger_balances')->insert([
        'ledger_id' => $record['ledger_id'],
        'currency' => 'NGN',
        'debit_minor_units' => 0,
        'credit_minor_units' => 5000,
        'balance_minor_units' => 5000,
        'updated_at' => now(),
    ]);
    allowAccountLifecycle();
    Sanctum::actingAs(accountLifecycleUser('funded-close-operator@example.com'), ['privileged']);

    $this->postJson("/api/v1/operations/accounts/{$record['account_id']}/close", [
        'reason' => 'bank_decision',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'An account must have a zero balance before it can be closed.');

    $this->assertDatabaseHas('accounts', [
        'id' => $record['account_id'],
        'status' => 'active',
        'closure_reason' => null,
    ]);
});

it('validates lifecycle reasons and rejects invalid state changes', function (): void {
    $record = createLifecycleAccount();
    allowAccountLifecycle();
    Sanctum::actingAs(accountLifecycleUser('validation-lifecycle-operator@example.com'), ['privileged']);
    $freezeUrl = "/api/v1/operations/accounts/{$record['account_id']}/freeze";

    $this->postJson($freezeUrl, ['reason' => 'because_i_said_so'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
    $this->postJson($freezeUrl, ['reason' => 'suspected_fraud'])->assertOk();
    $this->postJson($freezeUrl, ['reason' => 'suspected_fraud'])->assertConflict();
    $this->postJson("/api/v1/operations/accounts/{$record['account_id']}/close", [
        'reason' => 'temporary',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('requires privileged access and explicit lifecycle permissions', function (): void {
    $accountId = Uuid::generate()->value();
    $url = "/api/v1/operations/accounts/{$accountId}/freeze";

    $this->postJson($url, ['reason' => 'court_order'])->assertUnauthorized();

    Sanctum::actingAs(accountLifecycleUser('ordinary-lifecycle-operator@example.com'));
    $this->postJson($url, ['reason' => 'court_order'])->assertForbidden();

    Sanctum::actingAs(accountLifecycleUser('forbidden-lifecycle-operator@example.com'), ['privileged']);
    $this->postJson($url, ['reason' => 'court_order'])
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});
