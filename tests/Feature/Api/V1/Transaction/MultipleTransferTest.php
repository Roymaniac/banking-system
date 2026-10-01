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

function multipleTransferApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

/** @return array{account_id: string, ledger_id: string, number: string} */
function createMultipleTransferApiAccount(
    User $user,
    string $number,
    string $currency = 'NGN',
): array {
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Batch',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated();
    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => $currency,
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
        'currency' => $currency,
        'created_at' => '2026-10-01 08:00:00',
        'version' => 1,
    ]);

    return ['account_id' => $accountId, 'ledger_id' => $ledgerId, 'number' => $number];
}

function fundMultipleTransferApiLedger(string $ledgerId, int $minorUnits): void
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

function multipleTransferPayload(array $recipients, string $reference = 'BATCH-20261001-001'): array
{
    return ['reference' => $reference, 'recipients' => $recipients];
}

it('pays every recipient atomically and returns a safe batch receipt', function (): void {
    $senderUser = multipleTransferApiUser();
    $sender = createMultipleTransferApiAccount($senderUser, '1534567890');
    $first = createMultipleTransferApiAccount(multipleTransferApiUser(), '2534567890');
    $second = createMultipleTransferApiAccount(multipleTransferApiUser(), '3534567890');
    fundMultipleTransferApiLedger($sender['ledger_id'], 20000);
    Sanctum::actingAs($senderUser);

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => $first['number'], 'minor_units' => 3000],
            ['account_number' => $second['number'], 'minor_units' => 4500],
        ]),
    )->assertCreated()
        ->assertJsonPath('data.multiple_transfer.reference', 'BATCH-20261001-001')
        ->assertJsonPath('data.multiple_transfer.total_minor_units', 7500)
        ->assertJsonPath('data.multiple_transfer.recipient_count', 2)
        ->assertJsonPath('data.multiple_transfer.status', 'completed')
        ->assertJsonMissingPath('data.multiple_transfer.ledger_entry_id');

    $this->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $sender['ledger_id'],
        'balance_minor_units' => 12500,
    ])->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $first['ledger_id'],
        'balance_minor_units' => 3000,
    ])->assertDatabaseHas('ledger_balances', [
        'ledger_id' => $second['ledger_id'],
        'balance_minor_units' => 4500,
    ])->assertDatabaseCount('multiple_transfer_items', 2);
});

it('rolls back the complete batch when funds are insufficient', function (): void {
    $senderUser = multipleTransferApiUser();
    $sender = createMultipleTransferApiAccount($senderUser, '4534567890');
    $first = createMultipleTransferApiAccount(multipleTransferApiUser(), '5534567890');
    $second = createMultipleTransferApiAccount(multipleTransferApiUser(), '6534567890');
    fundMultipleTransferApiLedger($sender['ledger_id'], 5000);
    Sanctum::actingAs($senderUser);

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => $first['number'], 'minor_units' => 3000],
            ['account_number' => $second['number'], 'minor_units' => 3000],
        ]),
    )->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'The sender account does not have enough available funds for all recipients.',
        );

    $this->assertDatabaseCount('multiple_transfers', 0)
        ->assertDatabaseMissing('ledger_balances', ['ledger_id' => $first['ledger_id']])
        ->assertDatabaseMissing('ledger_balances', ['ledger_id' => $second['ledger_id']]);
});

it('rejects duplicate recipients and a sender included as a recipient', function (): void {
    $senderUser = multipleTransferApiUser();
    $sender = createMultipleTransferApiAccount($senderUser, '7534567890');
    $recipient = createMultipleTransferApiAccount(multipleTransferApiUser(), '8534567890');
    fundMultipleTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => $recipient['number'], 'minor_units' => 1000],
            ['account_number' => $recipient['number'], 'minor_units' => 1000],
        ]),
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['recipients.1.account_number']);

    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => $sender['number'], 'minor_units' => 1000],
        ], 'BATCH-20261001-002'),
    )->assertUnprocessable()
        ->assertJsonPath('message', 'The sender cannot also be a recipient.');
});

it('prevents foreign senders and unavailable recipient accounts', function (): void {
    $owner = multipleTransferApiUser();
    $sender = createMultipleTransferApiAccount($owner, '1634567890');
    fundMultipleTransferApiLedger($sender['ledger_id'], 10000);

    Sanctum::actingAs(multipleTransferApiUser());
    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => '2634567890', 'minor_units' => 1000],
        ]),
    )->assertNotFound()
        ->assertJsonPath('message', 'Account not found.');

    Sanctum::actingAs($owner);
    $this->postJson(
        "/api/v1/accounts/{$sender['account_id']}/multiple-transfers",
        multipleTransferPayload([
            ['account_number' => '2634567890', 'minor_units' => 1000],
        ]),
    )->assertUnprocessable()
        ->assertJsonPath('message', 'One or more recipient accounts are unavailable.');
});

it('prevents duplicate references and validates bounded input', function (): void {
    $senderUser = multipleTransferApiUser();
    $sender = createMultipleTransferApiAccount($senderUser, '3634567890');
    $recipient = createMultipleTransferApiAccount(multipleTransferApiUser(), '4634567890');
    fundMultipleTransferApiLedger($sender['ledger_id'], 10000);
    Sanctum::actingAs($senderUser);
    $payload = multipleTransferPayload([
        ['account_number' => $recipient['number'], 'minor_units' => 1000],
    ]);

    $this->postJson("/api/v1/accounts/{$sender['account_id']}/multiple-transfers", $payload)
        ->assertCreated();
    $this->postJson("/api/v1/accounts/{$sender['account_id']}/multiple-transfers", $payload)
        ->assertConflict();

    $this->postJson("/api/v1/accounts/{$sender['account_id']}/multiple-transfers", [
        'reference' => 'invalid reference!',
        'recipients' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reference', 'recipients']);

    $tooManyRecipients = array_map(
        fn (int $position): array => [
            'account_number' => sprintf('5%09d', $position),
            'minor_units' => 1,
        ],
        range(1, 21),
    );

    $this->postJson("/api/v1/accounts/{$sender['account_id']}/multiple-transfers", [
        'reference' => 'BATCH-TOO-LARGE',
        'recipients' => $tooManyRecipients,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['recipients']);
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/accounts/'.AccountId::generate()->value().'/multiple-transfers')
        ->assertUnauthorized();
});
