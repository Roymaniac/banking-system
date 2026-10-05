<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function trustedOperationUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

/** @return array{account_id: string, ledger_id: string} */
function createTrustedOperationAccount(string $number): array
{
    Sanctum::actingAs(trustedOperationUser());
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Operation',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');
    $ledgerId = Uuid::generate()->value();

    DB::table('accounts')->where('id', $accountId)->update([
        'number' => $number,
        'status' => 'active',
        'version' => 3,
    ]);
    DB::table('ledgers')->insert([
        'id' => $ledgerId,
        'account_id' => $accountId,
        'currency' => 'NGN',
        'created_at' => '2026-10-01 08:00:00',
        'version' => 1,
    ]);

    return ['account_id' => $accountId, 'ledger_id' => $ledgerId];
}

function allowTrustedOperations(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

it('requires authentication and the operation-specific permission', function (): void {
    $accountId = AccountId::generate()->value();

    $this->postJson("/api/v1/operations/accounts/{$accountId}/deposits")
        ->assertUnauthorized();

    Sanctum::actingAs(trustedOperationUser());
    $this->postJson("/api/v1/operations/accounts/{$accountId}/deposits", [
        'minor_units' => 1000,
        'reference' => 'STAFF-DEPOSIT-001',
    ])->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('posts a configured trusted deposit and returns a safe receipt', function (): void {
    $target = createTrustedOperationAccount('1734567890');
    $settlement = createTrustedOperationAccount('2734567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser());

    $this->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 5000,
        'reference' => 'STAFF-DEPOSIT-001',
    ])->assertCreated()
        ->assertJsonPath('data.deposit.reference', 'STAFF-DEPOSIT-001')
        ->assertJsonPath('data.deposit.minor_units', 5000)
        ->assertJsonPath('data.deposit.status', 'completed')
        ->assertJsonMissingPath('data.deposit.ledger_entry_id');

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $target['ledger_id'],
        'balance_minor_units' => 5000,
    ]);
});

it('posts a trusted withdrawal using the server-configured settlement ledger', function (): void {
    $target = createTrustedOperationAccount('3734567890');
    $settlement = createTrustedOperationAccount('4734567890');
    DB::table('ledger_balances')->insert([
        'ledger_id' => $target['ledger_id'],
        'currency' => 'NGN',
        'debit_minor_units' => 0,
        'credit_minor_units' => 8000,
        'balance_minor_units' => 8000,
        'updated_at' => now(),
    ]);
    config(['banking.settlement_ledgers.withdrawal.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser());

    $this->postJson("/api/v1/operations/accounts/{$target['account_id']}/withdrawals", [
        'minor_units' => 3000,
        'reference' => 'STAFF-WITHDRAWAL-001',
    ])->assertCreated()
        ->assertJsonPath('data.withdrawal.minor_units', 3000)
        ->assertJsonPath('data.withdrawal.status', 'completed');

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $target['ledger_id'],
        'balance_minor_units' => 5000,
    ]);
});

it('reverses a posted operation and prevents a second reversal', function (): void {
    $target = createTrustedOperationAccount('5734567890');
    $settlement = createTrustedOperationAccount('6734567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser());

    $this->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 5000,
        'reference' => 'REVERSIBLE-DEPOSIT-001',
    ])->assertCreated();
    $entryId = (string) DB::table('deposits')->value('ledger_entry_id');
    $payload = ['reference' => 'REVERSAL-001', 'reason' => 'Duplicate external settlement'];

    $this->postJson("/api/v1/operations/ledger-entries/{$entryId}/reversals", $payload)
        ->assertCreated()
        ->assertJsonPath('data.reversal.reference', 'REVERSAL-001')
        ->assertJsonPath('data.reversal.reason', 'Duplicate external settlement');
    $this->postJson("/api/v1/operations/ledger-entries/{$entryId}/reversals", [
        'reference' => 'REVERSAL-002',
        'reason' => 'Second attempt',
    ])->assertConflict();

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $target['ledger_id'],
        'balance_minor_units' => 0,
    ]);
});

it('fails safely when a settlement ledger is not configured', function (): void {
    $target = createTrustedOperationAccount('7734567890');
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser());

    $this->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 1000,
        'reference' => 'NO-SETTLEMENT-001',
    ])->assertStatus(503)
        ->assertJsonPath('message', 'Settlement is not configured for this account currency.');
});
