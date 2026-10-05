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

function balanceApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function createBalanceApiProfileAndAccount(): string
{
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();

    return test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');
}

function provisionBalanceApiLedger(string $accountId): string
{
    $accounts = app(AccountRepository::class);
    $account = $accounts->findById(new AccountId($accountId));
    expect($account)->not->toBeNull();

    $now = new DateTimeImmutable('2026-10-01T09:00:00+01:00');
    $account->assignNumber(new AccountNumber('1234567890'), $now, Uuid::generate());
    $accounts->save($account);
    $account->pullDomainEvents();

    $account->activate($now, Uuid::generate());
    $accounts->save($account);
    $account->pullDomainEvents();

    return app(CreateLedger::class)
        ->handle(new CreateLedgerCommand($account->id()))
        ->id()
        ->value();
}

it('reports that a pending account does not have a balance yet', function (): void {
    Sanctum::actingAs(balanceApiUser());
    $accountId = createBalanceApiProfileAndAccount();

    $this->getJson("/api/v1/accounts/{$accountId}/balance")
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'The account balance is unavailable until the account is active.',
        );
});

it('returns zero for a provisioned ledger with no posted entries', function (): void {
    Sanctum::actingAs(balanceApiUser());
    $accountId = createBalanceApiProfileAndAccount();
    provisionBalanceApiLedger($accountId);

    $this->getJson("/api/v1/accounts/{$accountId}/balance")
        ->assertOk()
        ->assertJsonPath('data.account_id', $accountId)
        ->assertJsonPath('data.balance.currency', 'NGN')
        ->assertJsonPath('data.balance.balance_minor_units', 0);
});

it('returns the projected posted balance without internal totals', function (): void {
    Sanctum::actingAs(balanceApiUser());
    $accountId = createBalanceApiProfileAndAccount();
    $ledgerId = provisionBalanceApiLedger($accountId);

    DB::table('ledger_balances')->insert([
        'ledger_id' => $ledgerId,
        'currency' => 'NGN',
        'debit_minor_units' => 2500,
        'credit_minor_units' => 15000,
        'balance_minor_units' => 12500,
        'updated_at' => now(),
    ]);

    $this->getJson("/api/v1/accounts/{$accountId}/balance")
        ->assertOk()
        ->assertJsonPath('data.balance.balance_minor_units', 12500)
        ->assertJsonMissingPath('data.balance.debit_minor_units')
        ->assertJsonMissingPath('data.balance.credit_minor_units')
        ->assertJsonMissingPath('data.balance.ledger_id');
});

it('does not reveal another customer account balance', function (): void {
    Sanctum::actingAs(balanceApiUser());
    $foreignAccountId = createBalanceApiProfileAndAccount();
    provisionBalanceApiLedger($foreignAccountId);

    Sanctum::actingAs(balanceApiUser());
    createBalanceApiProfileAndAccount();

    $this->getJson("/api/v1/accounts/{$foreignAccountId}/balance")
        ->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');
});

it('requires authentication to view an account balance', function (): void {
    $this->getJson('/api/v1/accounts/'.AccountId::generate()->value().'/balance')
        ->assertUnauthorized();
});
