<?php

declare(strict_types=1);

use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use App\Models\User;
use Identity\Application\MultiFactor\TotpAuthenticator;
use Identity\Domain\User\ValueObject\EmailAddress;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Ledger\Application\Creation\CreateLedger;
use Ledger\Application\Creation\CreateLedgerCommand;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function transferApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

/** @return array{account_id: string, ledger_id: string, number: string} */
function createTransferApiAccount(User $user, string $number, string $currency = 'NGN'): array
{
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();

    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => $currency,
    ])->assertCreated()->json('data.account.id');

    $accounts = app(AccountRepository::class);
    $account = $accounts->findById(new AccountId($accountId));
    expect($account)->not->toBeNull();

    $now = new DateTimeImmutable('2026-10-01T09:00:00+01:00');
    $account->assignNumber(new AccountNumber($number), $now, Uuid::generate());
    $accounts->save($account);
    $account->pullDomainEvents();
    $account->activate($now, Uuid::generate());
    $accounts->save($account);
    $account->pullDomainEvents();

    $ledger = app(CreateLedger::class)->handle(new CreateLedgerCommand($account->id()));

    return [
        'account_id' => $accountId,
        'ledger_id' => $ledger->id()->value(),
        'number' => $number,
    ];
}

function fundTransferApiLedger(string $ledgerId, int $minorUnits): void
{
    DB::table('ledger_balances')->insert([
        'ledger_id' => $ledgerId,
        'currency' => 'NGN',
        'debit_minor_units' => 0,
        'credit_minor_units' => $minorUnits,
        'balance_minor_units' => $minorUnits,
        'updated_at' => now(),
    ]);
}

function transferPayload(string $recipientNumber, array $changes = []): array
{
    return array_merge([
        'recipient_account_number' => $recipientNumber,
        'minor_units' => 2500,
        'reference' => 'mobile-20261001-001',
    ], $changes);
}

/** Gives each independent transfer its own safe retry identifier. */
function transferRequest(): mixed
{
    return test()->withHeader('Idempotency-Key', Uuid::generate()->value());
}

/** Enables a predictable authenticator so the step-up flow can be tested safely. */
function enableTransferMultiFactor(User $user): void
{
    app()->instance(TotpAuthenticator::class, new class implements TotpAuthenticator
    {
        public function generateSecret(): string
        {
            return 'TRANSFERTESTSECRET';
        }

        public function provisioningUri(EmailAddress $email, string $issuer, string $secret): string
        {
            return 'otpauth://totp/transfer-test';
        }

        public function verify(string $secret, string $code, DateTimeImmutable $at): bool
        {
            return $secret === 'TRANSFERTESTSECRET' && $code === '123456';
        }
    });

    DB::table('user_multi_factor_authentication')->insert([
        'user_id' => $user->identity_user_id,
        'encrypted_secret' => app('encrypter')->encrypt('TRANSFERTESTSECRET', false),
        'recovery_code_hashes' => json_encode([], JSON_THROW_ON_ERROR),
        'confirmed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('moves money between active accounts and returns a safe receipt', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '1234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '2234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertCreated()
        ->assertJsonPath('data.transfer.reference', 'MOBILE-20261001-001')
        ->assertJsonPath('data.transfer.minor_units', 2500)
        ->assertJsonPath('data.transfer.currency', 'NGN')
        ->assertJsonPath('data.transfer.status', 'completed')
        ->assertJsonMissingPath('data.transfer.ledger_entry_id');

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $sender['ledger_id'],
        'balance_minor_units' => 7500,
    ])->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $recipient['ledger_id'],
        'balance_minor_units' => 2500,
    ])->assertDatabaseCount('email_outbox', 2);

    $messages = app(EmailOutboxRepository::class)->pending(10);
    expect($messages)->toHaveCount(2)
        ->and($messages[0]->email()->body()->value())->toContain('account ending 7890')
        ->and($messages[0]->email()->body()->value())->toContain('NGN 25.00')
        ->and($messages[0]->email()->body()->value())->not->toContain($sender['number'])
        ->and($messages[1]->email()->body()->value())->not->toContain($recipient['number']);
});

it('moves a high-value transfer only after MFA approves those exact instructions', function (): void {
    config()->set('security.high_value_transfer.threshold_minor_units', 5000);
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '1034567890');
    $recipient = createTransferApiAccount(transferApiUser(), '2034567890');
    $otherRecipient = createTransferApiAccount(transferApiUser(), '3034567890');
    fundTransferApiLedger($sender['ledger_id'], 20000);
    enableTransferMultiFactor($senderUser);

    $customerToken = $senderUser->createToken('customer-phone', ['customer'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $idempotencyKey = Uuid::generate()->value();
    $payload = transferPayload($recipient['number'], ['minor_units' => 5000]);
    $url = "/api/v1/accounts/{$sender['account_id']}/transfers";

    $challengeResponse = $this->withToken($customerToken)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson($url, $payload)
        ->assertAccepted()
        ->assertJsonPath('data.mfa_required', true)
        ->assertJsonPath('data.purpose', 'high_value_transfer')
        ->assertJsonMissingPath('data.access_token');

    $challengeToken = (string) $challengeResponse->json('data.challenge_token');
    $this->assertDatabaseCount('transfers', 0)
        ->assertDatabaseCount('api_idempotency_records', 0)
        ->assertDatabaseHas('multi_factor_login_challenges', [
            'token_hash' => hash('sha256', $challengeToken),
            'purpose' => 'high_value_transfer',
        ]);

    $approvalResponse = $this->postJson('/api/v1/auth/mfa/challenge', [
        'challenge_token' => $challengeToken,
        'code' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.authorization', 'high_value_transfer');

    $approvalToken = (string) $approvalResponse->json('data.access_token');
    $this->assertDatabaseCount('email_outbox', 1);
    $this->app['auth']->forgetGuards();
    $this->withToken($approvalToken)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson($url, $payload)
        ->assertCreated();

    $this->assertDatabaseCount('transfers', 1);

    // Changing the recipient makes this a different instruction, so the old
    // approval token cannot authorize it.
    $this->app['auth']->forgetGuards();
    $this->withToken($approvalToken)
        ->withHeader('Idempotency-Key', Uuid::generate()->value())
        ->postJson($url, transferPayload($otherRecipient['number'], [
            'minor_units' => 5000,
            'reference' => 'mobile-20261001-002',
        ]))
        ->assertAccepted()
        ->assertJsonPath('data.mfa_required', true);

    $this->assertDatabaseCount('transfers', 1);
});

it('requires MFA enrollment before a customer can make a high-value transfer', function (): void {
    config()->set('security.high_value_transfer.threshold_minor_units', 5000);
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '4034567890');
    $recipient = createTransferApiAccount(transferApiUser(), '5034567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number'], ['minor_units' => 5000]),
    )->assertForbidden()
        ->assertJsonPath(
            'message',
            'Enable multi-factor authentication before making a high-value transfer.',
        );

    $this->assertDatabaseCount('transfers', 0);
});

it('prevents a customer from transferring from another customer account', function (): void {
    $owner = transferApiUser();
    $sender = createTransferApiAccount($owner, '3234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '4234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);

    Sanctum::actingAs(transferApiUser());

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');
});

it('rejects transfers when funds are insufficient', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '5234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '6234567890');
    fundTransferApiLedger($sender['ledger_id'], 1000);
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The sender account does not have enough available funds for this transfer.',
        );

    $this->assertDatabaseCount('transfers', 0);
});

it('blocks an unusual burst of outgoing transfers and records a safe risk event', function (): void {
    config()->set('security.transfer_risk.maximum_transfers', 2);
    config()->set('security.transfer_risk.velocity_window_minutes', 60);
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '5434567890');
    $recipient = createTransferApiAccount(transferApiUser(), '6434567890');
    fundTransferApiLedger($sender['ledger_id'], 5000);
    Sanctum::actingAs($senderUser);
    $url = "/api/v1/accounts/{$sender['account_id']}/transfers";

    foreach ([1, 2] as $position) {
        transferRequest()->postJson($url, transferPayload($recipient['number'], [
            'minor_units' => 1000,
            'reference' => "RISK-TRANSFER-00{$position}",
        ]))->assertCreated();
    }

    transferRequest()->postJson($url, transferPayload($recipient['number'], [
        'minor_units' => 1000,
        'reference' => 'RISK-TRANSFER-003',
    ]))->assertTooManyRequests()
        ->assertJsonPath(
            'message',
            'This transfer was blocked because the account has made too many recent transfers.',
        );

    $this->assertDatabaseCount('transfers', 2)
        ->assertDatabaseCount('transfer_risk_cases', 1)
        ->assertDatabaseHas('ledger_balances', [
            'ledger_id' => $sender['ledger_id'],
            'balance_minor_units' => 3000,
        ])->assertDatabaseHas('security_events', [
            'type' => 'transaction.transfer_velocity_blocked',
            'severity' => 'warning',
            'subject_id' => $sender['account_id'],
        ]);

    $details = json_decode((string) DB::table('security_events')->sole()->details, true);
    expect($details)->toMatchArray([
        'rule' => 'outgoing_transfer_velocity',
        'window_minutes' => 60,
        'maximum_transfers' => 2,
    ])->not->toHaveKey('minor_units');
});

it('prevents duplicate references and transfers to the same account', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '7234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '8234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertCreated();
    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertConflict();

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($sender['number'], ['reference' => 'mobile-20261001-002']),
    )->assertUnprocessable()
        ->assertJsonPath('message', 'The sender and recipient must be different accounts.');
});

it('rejects unavailable recipients and currency mismatches', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '1334567890');
    $dollarRecipient = createTransferApiAccount(transferApiUser(), '2334567890', 'USD');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload('3334567890'),
    )->assertUnprocessable()
        ->assertJsonPath('message', 'The recipient account is unavailable.');

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($dollarRecipient['number']),
    )->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The sender and recipient accounts must use the same currency.',
        );
});

it('validates public transfer input and requires authentication', function (): void {
    $this->postJson('/api/v1/accounts/'.AccountId::generate()->value().'/transfers')
        ->assertUnauthorized();

    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '9234567890');
    Sanctum::actingAs($senderUser);

    transferRequest()->postJson("/api/v1/accounts/{$sender['account_id']}/transfers", [
        'recipient_account_number' => 'invalid',
        'minor_units' => 0,
        'reference' => 'unsafe reference!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'recipient_account_number',
            'minor_units',
            'reference',
        ]);
});
