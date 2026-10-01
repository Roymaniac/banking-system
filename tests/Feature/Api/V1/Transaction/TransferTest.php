<?php

declare(strict_types=1);

use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Ledger\Application\Creation\CreateLedger;
use Ledger\Application\Creation\CreateLedgerCommand;
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

it('moves money between active accounts and returns a safe receipt', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '1234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '2234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    $this->postJson(
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
    ]);
});

it('prevents a customer from transferring from another customer account', function (): void {
    $owner = transferApiUser();
    $sender = createTransferApiAccount($owner, '3234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '4234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);

    Sanctum::actingAs(transferApiUser());

    $this->postJson(
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

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The sender account does not have enough available funds for this transfer.',
        );

    $this->assertDatabaseCount('transfers', 0);
});

it('prevents duplicate references and transfers to the same account', function (): void {
    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '7234567890');
    $recipient = createTransferApiAccount(transferApiUser(), '8234567890');
    fundTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertCreated();
    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/transfers",
        transferPayload($recipient['number']),
    )->assertConflict();

    $this->postJson(
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

    $this->postJson(
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
    $this->postJson('/api/v1/accounts/' . AccountId::generate()->value() . '/transfers')
        ->assertUnauthorized();

    $senderUser = transferApiUser();
    $sender = createTransferApiAccount($senderUser, '9234567890');
    Sanctum::actingAs($senderUser);

    $this->postJson("/api/v1/accounts/{$sender['account_id']}/transfers", [
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
