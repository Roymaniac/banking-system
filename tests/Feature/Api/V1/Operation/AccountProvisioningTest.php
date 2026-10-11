<?php

declare(strict_types=1);

use Account\Application\Provisioning\ProvisionAccount;
use Account\Application\Provisioning\ProvisionAccountCommand;
use Account\Domain\Account\ValueObject\AccountId;
use App\Models\User;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

function provisioningUser(string $email): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => $email,
        'email_verified_at' => now(),
    ]);
}

/** @return array{customer_id: string, account_id: string} */
function createPendingProvisioningAccount(bool $identityApproved): array
{
    $customer = provisioningUser('provisioning-customer@example.com');
    Sanctum::actingAs($customer);
    $customerId = test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Pending',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated()->json('data.customer.id');
    $accountId = test()->postJson('/api/v1/accounts', [
        'type' => 'savings',
        'currency' => 'NGN',
    ])->assertCreated()->json('data.account.id');

    if ($identityApproved) {
        DB::table('customer_due_diligence_cases')->insert([
            'id' => Uuid::generate()->value(),
            'customer_id' => $customerId,
            'status' => 'approved',
            'document_type' => 'passport',
            'issuing_country' => 'NG',
            'document_number_encrypted' => 'encrypted-test-value',
            'document_fingerprint' => hash('sha256', $customerId),
            'document_last_four' => '6789',
            'risk_level' => 'standard',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => UserId::generate()->value(),
            'rejection_reason' => null,
            'version' => 2,
        ]);
    }

    return ['customer_id' => $customerId, 'account_id' => $accountId];
}

function allowAccountProvisioning(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return $permission->value() === 'accounts.provision';
        }
    });
}

it('atomically activates an approved customer account and creates its ledger', function (): void {
    $pending = createPendingProvisioningAccount(true);
    allowAccountProvisioning();
    Sanctum::actingAs(provisioningUser('provisioning-operator@example.com'), ['privileged']);

    $response = $this->postJson("/api/v1/operations/accounts/{$pending['account_id']}/provision")
        ->assertOk()
        ->assertJsonPath('data.provisioning.account.id', $pending['account_id'])
        ->assertJsonPath('data.provisioning.account.status', 'active')
        ->assertJsonPath('data.provisioning.ledger.account_id', $pending['account_id'])
        ->assertJsonPath('data.provisioning.ledger.currency', 'NGN');

    expect($response->json('data.provisioning.account.number'))
        ->toMatch('/^[1-9][0-9]{9}$/');
    $this->assertDatabaseHas('accounts', [
        'id' => $pending['account_id'],
        'status' => 'active',
        'version' => 3,
    ]);
    $this->assertDatabaseHas('ledgers', ['account_id' => $pending['account_id']]);
    $this->assertDatabaseHas('audit_log', [
        'event_name' => 'account.activated',
        'aggregate_id' => $pending['account_id'],
    ]);
});

it('leaves every provisioning record unchanged when identity approval is missing', function (): void {
    $pending = createPendingProvisioningAccount(false);
    allowAccountProvisioning();
    Sanctum::actingAs(provisioningUser('blocked-provisioning-operator@example.com'), ['privileged']);

    $this->postJson("/api/v1/operations/accounts/{$pending['account_id']}/provision")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Customer identity verification must be approved before the account can be activated.');

    $this->assertDatabaseHas('accounts', [
        'id' => $pending['account_id'],
        'number' => null,
        'status' => 'pending',
        'version' => 1,
    ]);
    $this->assertDatabaseMissing('ledgers', ['account_id' => $pending['account_id']]);
});

it('requires privileged access and the account provisioning permission', function (): void {
    $accountId = AccountId::generate()->value();

    $this->postJson("/api/v1/operations/accounts/{$accountId}/provision")
        ->assertUnauthorized();

    Sanctum::actingAs(provisioningUser('ordinary-provisioning-operator@example.com'));
    $this->postJson("/api/v1/operations/accounts/{$accountId}/provision")
        ->assertForbidden();

    Sanctum::actingAs(provisioningUser('unauthorized-provisioning-operator@example.com'), ['privileged']);
    $this->postJson("/api/v1/operations/accounts/{$accountId}/provision")
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('does not provision the same account twice', function (): void {
    $pending = createPendingProvisioningAccount(true);
    allowAccountProvisioning();
    Sanctum::actingAs(provisioningUser('retry-provisioning-operator@example.com'), ['privileged']);
    $url = "/api/v1/operations/accounts/{$pending['account_id']}/provision";

    $this->postJson($url)->assertOk();
    $this->postJson($url)->assertConflict();

    expect(DB::table('ledgers')->where('account_id', $pending['account_id'])->count())->toBe(1);
});

it('rolls back the account changes when ledger creation cannot be stored', function (): void {
    $pending = createPendingProvisioningAccount(true);
    $ledgers = Mockery::mock(LedgerRepository::class);
    $ledgers->shouldReceive('existsForAccount')->once()->andReturnFalse();
    $ledgers->shouldReceive('save')
        ->once()
        ->with(Mockery::type(Ledger::class))
        ->andThrow(new RuntimeException('Simulated ledger storage failure.'));
    app()->instance(LedgerRepository::class, $ledgers);

    expect(fn () => app(ProvisionAccount::class)->handle(
        new ProvisionAccountCommand(new AccountId($pending['account_id'])),
    ))->toThrow(RuntimeException::class, 'Simulated ledger storage failure.');

    // The number and activation saves happened earlier inside the same
    // transaction, so this proves they were rolled back as well.
    $this->assertDatabaseHas('accounts', [
        'id' => $pending['account_id'],
        'number' => null,
        'status' => 'pending',
        'version' => 1,
    ]);
    $this->assertDatabaseMissing('ledgers', ['account_id' => $pending['account_id']]);
});
