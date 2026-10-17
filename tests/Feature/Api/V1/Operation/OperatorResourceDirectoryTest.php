<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoBankingSeeder;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function allowOperatorResourcePermissions(string ...$allowed): void
{
    app()->instance(AuthorizationChecker::class, new class($allowed) implements AuthorizationChecker
    {
        /** @param list<string> $allowed */
        public function __construct(private array $allowed) {}

        public function allows(UserId $userId, Permission $permission): bool
        {
            return in_array($permission->value(), $this->allowed, true);
        }
    });
}

function signInResourceOperator(): User
{
    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
    Sanctum::actingAs($user, ['privileged']);

    return $user;
}

beforeEach(function (): void {
    $this->seed(DemoBankingSeeder::class);
    signInResourceOperator();
});

it('searches bounded accounts by purpose and resolves an unnumbered selection', function (): void {
    allowOperatorResourcePermissions('transactions.deposit', 'accounts.provision', 'audit.view');

    $this->getJson('/api/v1/operations/accounts?purpose=deposit&search=Ada&per_page=1')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.account_last_four', '0001')
        ->assertJsonPath('data.items.0.eligible', true)
        ->assertJsonPath('meta.per_page', 1);

    $pendingId = (string) Str::uuid();
    DB::table('accounts')->insert([
        'id' => $pendingId,
        'customer_id' => '20000000-0000-4000-8000-000000000001',
        'type' => 'savings',
        'currency' => 'NGN',
        'number' => null,
        'status' => 'pending',
        'created_at' => now(),
        'version' => 1,
    ]);

    $this->getJson("/api/v1/operations/accounts/{$pendingId}?purpose=provision")
        ->assertOk()
        ->assertJsonPath('data.account.id', $pendingId)
        ->assertJsonPath('data.account.account_last_four', null)
        ->assertJsonPath('data.account.eligible', true)
        ->assertJsonPath('data.account.ineligibility_reason_code', null);

    $this->getJson('/api/v1/operations/accounts?purpose=audit&search=Demo%20Settlement')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

it('uses the requested account purpose permission and validates bounded filters', function (): void {
    allowOperatorResourcePermissions('accounts.close');

    $this->getJson('/api/v1/operations/accounts?purpose=deposit')
        ->assertForbidden();

    $this->getJson('/api/v1/operations/accounts?purpose=unknown&page=0&per_page=51')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['purpose', 'page', 'per_page']);
});

it('searches customers using minimal masked identity data', function (): void {
    allowOperatorResourcePermissions('customer_reports.view');
    DB::table('customer_contacts')->insert([
        'id' => (string) Str::uuid(),
        'customer_id' => '20000000-0000-4000-8000-000000000001',
        'type' => 'email',
        'value' => 'ada.lookup@example.com',
    ]);

    $this->getJson('/api/v1/operations/customers?search=ada.lookup%40example.com')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.display_name', 'Ada Okafor')
        ->assertJsonPath('data.items.0.masked_contact', 'a•••@example.com')
        ->assertJsonMissingPath('data.items.0.date_of_birth');

    $this->getJson('/api/v1/operations/customers/20000000-0000-4000-8000-000000000001')
        ->assertOk()
        ->assertJsonPath('data.customer.account_count', 1);

    $this->getJson('/api/v1/operations/customers?search=Demo%20Settlement')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

it('lists only verified unlinked staff candidates and rechecks onboarding eligibility', function (): void {
    allowOperatorResourcePermissions('staff.manage');
    $candidateId = (string) Str::uuid();
    User::factory()->create([
        'identity_user_id' => $candidateId,
        'name' => 'Nkiru Candidate',
        'email' => 'nkiru.candidate@example.com',
        'email_verified_at' => now(),
    ]);
    $unverifiedId = (string) Str::uuid();
    User::factory()->create([
        'identity_user_id' => $unverifiedId,
        'name' => 'Unverified Candidate',
        'email_verified_at' => null,
    ]);

    $this->getJson('/api/v1/administration/staff-candidates?search=Nkiru')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.user_id', $candidateId)
        ->assertJsonPath('data.items.0.masked_email', 'n•••@example.com')
        ->assertJsonPath('data.items.0.eligible', true);

    $this->getJson('/api/v1/administration/staff-candidates/10000000-0000-4000-8000-000000000003')
        ->assertOk()
        ->assertJsonPath('data.staff_candidate.eligible', false)
        ->assertJsonPath('data.staff_candidate.ineligibility_reason_code', 'already_staff');

    $this->getJson('/api/v1/administration/staff-candidates?search=Demo%20Settlement')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    $this->postJson('/api/v1/administration/staff', [
        'user_id' => $unverifiedId,
        'employee_number' => 'EMP-UNVERIFIED',
        'department_id' => '80000000-0000-4000-8000-000000000001',
        'job_title' => 'Operations Analyst',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'A verified sign-in identity is required before staff can be hired.');
});

it('searches original ledger entries and resolves the exact reversible entry', function (): void {
    allowOperatorResourcePermissions('transactions.reverse');

    $response = $this->getJson('/api/v1/operations/ledger-entries?purpose=reversal&search=DEMO-FUNDING-001')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.entry_id', '50000000-0000-4000-8000-000000000001')
        ->assertJsonPath('data.items.0.reference', 'DEMO-FUNDING-001')
        ->assertJsonPath('data.items.0.transaction_type', 'deposit')
        ->assertJsonPath('data.items.0.minor_units', 25000000)
        ->assertJsonPath('data.items.0.reversible', true);

    $entryId = $response->json('data.items.0.entry_id');
    $this->getJson("/api/v1/operations/ledger-entries/{$entryId}")
        ->assertOk()
        ->assertJsonPath('data.ledger_entry.entry_id', $entryId)
        ->assertJsonCount(2, 'data.ledger_entry.postings');
});

it('requires authentication privileged access and the lookup permission', function (): void {
    auth()->guard()->forgetUser();
    $this->getJson('/api/v1/operations/customers')->assertUnauthorized();

    Sanctum::actingAs(User::query()->where('email', 'admin@example.com')->firstOrFail());
    allowOperatorResourcePermissions('customer_reports.view');
    $this->getJson('/api/v1/operations/customers')->assertForbidden();
});
