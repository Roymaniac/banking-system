<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use App\Support\Idempotency\PruneExpiredIdempotencyRecords;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Notification\CustomerTransactionNotifier;

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
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
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

/** Gives each independent money request its own safe retry identifier. */
function trustedOperationRequest(): mixed
{
    return test()->withHeader('Idempotency-Key', Uuid::generate()->value());
}

it('requires authentication and the operation-specific permission', function (): void {
    $accountId = AccountId::generate()->value();

    $this->postJson("/api/v1/operations/accounts/{$accountId}/deposits")
        ->assertUnauthorized();

    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
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
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    trustedOperationRequest()->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
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
    ])->assertDatabaseCount('email_outbox', 1);
});

it('rolls back a deposit when its durable customer alert cannot be recorded', function (): void {
    $target = createTrustedOperationAccount('1834567891');
    $settlement = createTrustedOperationAccount('2834567891');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    $notifications = Mockery::mock(CustomerTransactionNotifier::class);
    $notifications->shouldReceive('sendToAccount')
        ->once()
        ->andThrow(new RuntimeException('Simulated transaction alert storage failure.'));
    app()->instance(CustomerTransactionNotifier::class, $notifications);

    $this->withoutExceptionHandling();

    expect(fn () => trustedOperationRequest()->postJson(
        "/api/v1/operations/accounts/{$target['account_id']}/deposits",
        [
            'minor_units' => 5000,
            'reference' => 'FAILED-ALERT-DEPOSIT-001',
        ],
    ))->toThrow(RuntimeException::class, 'Simulated transaction alert storage failure.');

    $this->assertDatabaseMissing('deposits', ['reference' => 'FAILED-ALERT-DEPOSIT-001'])
        ->assertDatabaseMissing('ledger_balances', ['ledger_id' => $target['ledger_id']])
        ->assertDatabaseCount('email_outbox', 0);
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
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    trustedOperationRequest()->postJson("/api/v1/operations/accounts/{$target['account_id']}/withdrawals", [
        'minor_units' => 3000,
        'reference' => 'STAFF-WITHDRAWAL-001',
    ])->assertCreated()
        ->assertJsonPath('data.withdrawal.minor_units', 3000)
        ->assertJsonPath('data.withdrawal.status', 'completed');

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $target['ledger_id'],
        'balance_minor_units' => 5000,
    ])->assertDatabaseCount('email_outbox', 1);
});

it('reverses a posted operation and prevents a second reversal', function (): void {
    $target = createTrustedOperationAccount('5734567890');
    $settlement = createTrustedOperationAccount('6734567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    trustedOperationRequest()->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 5000,
        'reference' => 'REVERSIBLE-DEPOSIT-001',
    ])->assertCreated();
    $entryId = (string) DB::table('deposits')->value('ledger_entry_id');
    $payload = ['reference' => 'REVERSAL-001', 'reason' => 'Duplicate external settlement'];

    trustedOperationRequest()->postJson("/api/v1/operations/ledger-entries/{$entryId}/reversals", $payload)
        ->assertCreated()
        ->assertJsonPath('data.reversal.reference', 'REVERSAL-001')
        ->assertJsonPath('data.reversal.reason', 'Duplicate external settlement');
    trustedOperationRequest()->postJson("/api/v1/operations/ledger-entries/{$entryId}/reversals", [
        'reference' => 'REVERSAL-002',
        'reason' => 'Second attempt',
    ])->assertConflict();

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $target['ledger_id'],
        'balance_minor_units' => 0,
    ])->assertDatabaseCount('email_outbox', 2);
});

it('fails safely when a settlement ledger is not configured', function (): void {
    $target = createTrustedOperationAccount('7734567890');
    config(['banking.settlement_ledgers.deposit.NGN' => null]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    trustedOperationRequest()->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 1000,
        'reference' => 'NO-SETTLEMENT-001',
    ])->assertStatus(503)
        ->assertJsonPath('message', 'Settlement is not configured for this account currency.');
});

it('blocks financial writes while leaving the API available for investigation', function (): void {
    $target = createTrustedOperationAccount('8734567890');
    $settlement = createTrustedOperationAccount('9734567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
    $this->artisan('banking:money-movement:suspend', [
        'reason' => 'Financial integrity investigation is in progress.',
    ])->assertSuccessful();

    trustedOperationRequest()->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 5000,
        'reference' => 'SUSPENDED-DEPOSIT-001',
    ])->assertServiceUnavailable()
        ->assertJsonPath('message', 'Money movement is temporarily suspended.');

    $this->assertDatabaseMissing('deposits', ['reference' => 'SUSPENDED-DEPOSIT-001']);
    $this->get('/up')->assertOk();
});

it('requires a safe idempotency key for an authorized money request', function (): void {
    $target = createTrustedOperationAccount('1834567890');
    $settlement = createTrustedOperationAccount('2834567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);

    $this->postJson("/api/v1/operations/accounts/{$target['account_id']}/deposits", [
        'minor_units' => 5000,
        'reference' => 'MISSING-KEY-DEPOSIT-001',
    ])->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The Idempotency-Key header is required and must contain 8 to 100 safe characters.',
        );

    $this->assertDatabaseMissing('deposits', ['reference' => 'MISSING-KEY-DEPOSIT-001']);
});

it('replays the first successful response without posting money twice', function (): void {
    $target = createTrustedOperationAccount('1934567890');
    $settlement = createTrustedOperationAccount('2934567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
    $key = Uuid::generate()->value();
    $payload = ['minor_units' => 5000, 'reference' => 'RETRIED-DEPOSIT-001'];
    $url = "/api/v1/operations/accounts/{$target['account_id']}/deposits";

    $first = $this->withHeader('Idempotency-Key', $key)->postJson($url, $payload);
    $second = $this->withHeader('Idempotency-Key', $key)->postJson($url, $payload);

    $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
    $second->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertExactJson($first->json());
    $this->assertDatabaseCount('deposits', 1)
        ->assertDatabaseHas('ledger_balances', [
            'ledger_id' => $target['ledger_id'],
            'balance_minor_units' => 5000,
        ]);
});

it('rejects an idempotency key reused for different money instructions', function (): void {
    $target = createTrustedOperationAccount('1034567890');
    $settlement = createTrustedOperationAccount('2034567890');
    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
    $key = Uuid::generate()->value();
    $url = "/api/v1/operations/accounts/{$target['account_id']}/deposits";

    $this->withHeader('Idempotency-Key', $key)->postJson($url, [
        'minor_units' => 5000,
        'reference' => 'KEY-MISMATCH-DEPOSIT-001',
    ])->assertCreated();

    $this->withHeader('Idempotency-Key', $key)->postJson($url, [
        'minor_units' => 9000,
        'reference' => 'KEY-MISMATCH-DEPOSIT-002',
    ])->assertConflict()
        ->assertJsonPath(
            'message',
            'This idempotency key was already used with different request data.',
        );

    $this->assertDatabaseCount('deposits', 1)
        ->assertDatabaseHas('ledger_balances', [
            'ledger_id' => $target['ledger_id'],
            'balance_minor_units' => 5000,
        ]);
});

it('allows the same key to retry after an unsuccessful response', function (): void {
    $target = createTrustedOperationAccount('1134567890');
    $settlement = createTrustedOperationAccount('2134567890');
    config(['banking.settlement_ledgers.deposit.NGN' => null]);
    allowTrustedOperations();
    Sanctum::actingAs(trustedOperationUser(), ['privileged']);
    $key = Uuid::generate()->value();
    $url = "/api/v1/operations/accounts/{$target['account_id']}/deposits";
    $payload = ['minor_units' => 5000, 'reference' => 'RECOVERED-DEPOSIT-001'];

    $this->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
        ->assertStatus(503);

    config(['banking.settlement_ledgers.deposit.NGN' => $settlement['ledger_id']]);

    $this->withHeader('Idempotency-Key', $key)->postJson($url, $payload)
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    $this->assertDatabaseCount('deposits', 1);
});

it('prunes expired idempotency records without removing live retry responses', function (): void {
    $baseRecord = [
        'actor_id' => UserId::generate()->value(),
        'route' => 'api.v1.operations.deposits.store',
        'key_hash' => hash('sha256', 'pruning-test-key'),
        'request_hash' => hash('sha256', 'pruning-test-request'),
        'state' => 'completed',
        'response_status' => 201,
        'response_body' => '{}',
        'content_type' => 'application/json',
        'created_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
    ];

    DB::table('api_idempotency_records')->insert([
        [...$baseRecord, 'id' => Uuid::generate()->value(), 'expires_at' => now()->subMinute()],
        [
            ...$baseRecord,
            'id' => Uuid::generate()->value(),
            'key_hash' => hash('sha256', 'live-pruning-test-key'),
            'expires_at' => now()->addHour(),
        ],
    ]);

    expect(app(PruneExpiredIdempotencyRecords::class)->prune())->toBe(1);
    $this->assertDatabaseCount('api_idempotency_records', 1);
});
