<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function historyApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

/** @return array{account_id: string, ledger_id: string} */
function createHistoryApiAccount(User $user, string $number): array
{
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Test',
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
        'created_at' => '2026-09-01 08:00:00',
        'version' => 1,
    ]);

    return ['account_id' => $accountId, 'ledger_id' => $ledgerId];
}

function addHistoryApiEntry(
    string $ledgerId,
    string $reference,
    string $side,
    int $minorUnits,
    string $occurredAt,
): void {
    $entryId = Uuid::generate()->value();

    DB::table('ledger_entries')->insert([
        'id' => $entryId,
        'ledger_id' => $ledgerId,
        'reference' => $reference,
        'description' => 'Statement entry '.$reference,
        'occurred_at' => $occurredAt,
        'recorded_at' => $occurredAt,
        'status' => 'posted',
        'version' => 2,
    ]);
    DB::table('ledger_postings')->insert([
        'id' => Uuid::generate()->value(),
        'entry_id' => $entryId,
        'ledger_id' => $ledgerId,
        'side' => $side,
        'minor_units' => $minorUnits,
        'currency' => 'NGN',
    ]);
}

it('returns bounded transaction history with totals and running balances', function (): void {
    $user = historyApiUser();
    $account = createHistoryApiAccount($user, '1434567890');
    addHistoryApiEntry($account['ledger_id'], 'CREDIT-001', 'credit', 10000, '2026-09-01 09:00:00');
    addHistoryApiEntry($account['ledger_id'], 'DEBIT-001', 'debit', 2500, '2026-09-02 09:00:00');
    Sanctum::actingAs($user);

    $firstPage = $this->getJson(
        "/api/v1/accounts/{$account['account_id']}/transactions?from=2026-09-01&to=2026-09-30&page=1&per_page=1",
    )->assertOk()
        ->assertJsonPath('data.history.account_number', '1434567890')
        ->assertJsonPath('data.history.currency', 'NGN')
        ->assertJsonPath('data.history.summary.opening_balance_minor_units', 0)
        ->assertJsonPath('data.history.summary.total_debit_minor_units', 2500)
        ->assertJsonPath('data.history.summary.total_credit_minor_units', 10000)
        ->assertJsonPath('data.history.summary.closing_balance_minor_units', 7500)
        ->assertJsonPath('data.history.pagination.total', 2)
        ->assertJsonPath('data.history.pagination.has_next_page', true)
        ->assertJsonPath('data.history.transactions.0.reference', 'CREDIT-001')
        ->assertJsonPath('data.history.transactions.0.balance_after_minor_units', 10000)
        ->assertJsonMissingPath('data.history.transactions.0.ledger_entry_id');

    expect($firstPage->json('data.history.transactions'))->toHaveCount(1);

    $this->getJson(
        "/api/v1/accounts/{$account['account_id']}/transactions?from=2026-09-01&to=2026-09-30&page=2&per_page=1",
    )->assertOk()
        ->assertJsonPath('data.history.transactions.0.reference', 'DEBIT-001')
        ->assertJsonPath('data.history.transactions.0.balance_after_minor_units', 7500)
        ->assertJsonPath('data.history.pagination.has_next_page', false);
});

it('calculates the opening balance before the requested period', function (): void {
    $user = historyApiUser();
    $account = createHistoryApiAccount($user, '2434567890');
    addHistoryApiEntry($account['ledger_id'], 'BEFORE-001', 'credit', 10000, '2026-09-01 09:00:00');
    addHistoryApiEntry($account['ledger_id'], 'IN-PERIOD-001', 'debit', 2500, '2026-09-02 09:00:00');
    Sanctum::actingAs($user);

    $this->getJson(
        "/api/v1/accounts/{$account['account_id']}/transactions?from=2026-09-02&to=2026-09-02",
    )->assertOk()
        ->assertJsonPath('data.history.summary.opening_balance_minor_units', 10000)
        ->assertJsonPath('data.history.summary.closing_balance_minor_units', 7500)
        ->assertJsonPath('data.history.transactions.0.reference', 'IN-PERIOD-001');
});

it('does not reveal another customer transaction history', function (): void {
    $owner = historyApiUser();
    $account = createHistoryApiAccount($owner, '3434567890');

    Sanctum::actingAs(historyApiUser());

    $this->getJson(
        "/api/v1/accounts/{$account['account_id']}/transactions?from=2026-09-01&to=2026-09-30",
    )->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');
});

it('validates the date range and bounded pagination', function (): void {
    $user = historyApiUser();
    $account = createHistoryApiAccount($user, '4434567890');
    Sanctum::actingAs($user);

    $this->getJson(
        "/api/v1/accounts/{$account['account_id']}/transactions?from=2026-09-30&to=2026-09-01&page=0&per_page=101",
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'page', 'per_page']);
});

it('requires authentication and a provisioned ledger', function (): void {
    $this->getJson(
        '/api/v1/accounts/'.AccountId::generate()->value().'/transactions?from=2026-09-01&to=2026-09-30',
    )->assertUnauthorized();

    $user = historyApiUser();
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Pending',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');

    $this->getJson(
        "/api/v1/accounts/{$accountId}/transactions?from=2026-09-01&to=2026-09-30",
    )->assertConflict()
        ->assertJsonPath(
            'message',
            'Transaction history is unavailable until the account is active.',
        );
});
