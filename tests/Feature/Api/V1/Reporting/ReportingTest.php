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

function reportingApiUser(): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email_verified_at' => now(),
    ]);
}

function allowReportingApi(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

/** @return array{customer: string, account: string, ledger: string} */
function reportingAccount(string $number): array
{
    $customerId = Uuid::generate()->value();
    $accountId = Uuid::generate()->value();
    $ledgerId = Uuid::generate()->value();

    DB::table('customers')->insert([
        'id' => $customerId,
        'user_id' => Uuid::generate()->value(),
        'first_name' => 'Ada',
        'middle_name' => null,
        'last_name' => 'Okafor',
        'date_of_birth' => '1992-05-10',
        'registered_at' => '2026-09-01 09:00:00',
        'version' => 1,
    ]);
    DB::table('accounts')->insert([
        'id' => $accountId,
        'customer_id' => $customerId,
        'number' => $number,
        'type' => 'savings',
        'currency' => 'NGN',
        'status' => 'active',
        'created_at' => '2026-09-01 09:00:00',
        'version' => 1,
    ]);
    DB::table('ledgers')->insert([
        'id' => $ledgerId,
        'account_id' => $accountId,
        'currency' => 'NGN',
        'created_at' => '2026-09-01 09:00:00',
        'version' => 1,
    ]);

    return ['customer' => $customerId, 'account' => $accountId, 'ledger' => $ledgerId];
}

function reportingEntry(string $debitLedger, string $creditLedger): string
{
    $entryId = Uuid::generate()->value();
    DB::table('ledger_entries')->insert([
        'id' => $entryId,
        'ledger_id' => $debitLedger,
        'reference' => 'REPORT-ENTRY-1',
        'description' => 'Reporting test entry',
        'occurred_at' => '2026-09-15 23:30:00',
        'recorded_at' => '2026-09-15 23:31:00',
        'status' => 'posted',
        'version' => 1,
    ]);
    DB::table('ledger_postings')->insert([
        [
            'id' => Uuid::generate()->value(),
            'entry_id' => $entryId,
            'ledger_id' => $debitLedger,
            'side' => 'debit',
            'minor_units' => 2500,
            'currency' => 'NGN',
        ],
        [
            'id' => Uuid::generate()->value(),
            'entry_id' => $entryId,
            'ledger_id' => $creditLedger,
            'side' => 'credit',
            'minor_units' => 2500,
            'currency' => 'NGN',
        ],
    ]);

    return $entryId;
}

it('protects reporting endpoints with authentication and permissions', function (): void {
    $this->getJson('/api/v1/reports/ledger?from=2026-09-01&to=2026-09-30')
        ->assertUnauthorized();

    Sanctum::actingAs(reportingApiUser());

    $this->getJson('/api/v1/reports/ledger?from=2026-09-01&to=2026-09-30')
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to perform this action.');
});

it('returns a complete customer report', function (): void {
    allowReportingApi();
    Sanctum::actingAs(reportingApiUser());
    $record = reportingAccount('1000000001');
    DB::table('customer_contacts')->insert([
        'id' => Uuid::generate()->value(),
        'customer_id' => $record['customer'],
        'type' => 'email',
        'value' => 'ada@example.test',
    ]);
    DB::table('customer_addresses')->insert([
        'id' => Uuid::generate()->value(),
        'customer_id' => $record['customer'],
        'type' => 'home',
        'line_one' => '1 Bank Street',
        'line_two' => null,
        'city' => 'Lagos',
        'state_or_region' => 'Lagos',
        'postal_code' => '100001',
        'country_code' => 'NG',
    ]);

    $this->getJson("/api/v1/reports/customers/{$record['customer']}")
        ->assertOk()
        ->assertJsonPath('data.report.full_name', 'Ada Okafor')
        ->assertJsonPath('data.report.account_count', 1)
        ->assertJsonPath('data.report.contacts.0.value', 'ada@example.test')
        ->assertJsonPath('data.report.addresses.0.city', 'Lagos')
        ->assertJsonPath('data.report.accounts.0.number', '1000000001');
});

it('returns balanced ledger controls and includes the full end date', function (): void {
    allowReportingApi();
    Sanctum::actingAs(reportingApiUser());
    $debit = reportingAccount('1000000002');
    $credit = reportingAccount('1000000003');
    reportingEntry($debit['ledger'], $credit['ledger']);

    $this->getJson('/api/v1/reports/ledger?from=2026-09-15&to=2026-09-15&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.report.control_totals.posted_entries', 1)
        ->assertJsonPath('data.report.control_totals.unbalanced_posted_entries', 0)
        ->assertJsonPath('data.report.currency_summaries.0.balanced', true)
        ->assertJsonPath('data.report.entries.0.reference', 'REPORT-ENTRY-1')
        ->assertJsonPath('data.report.pagination.total', 1);
});

it('returns a paginated transaction report with exact balances', function (): void {
    allowReportingApi();
    Sanctum::actingAs(reportingApiUser());
    $debit = reportingAccount('1000000004');
    $credit = reportingAccount('1000000005');
    reportingEntry($debit['ledger'], $credit['ledger']);

    $this->getJson("/api/v1/reports/accounts/{$debit['account']}/transactions?from=2026-09-15&to=2026-09-15")
        ->assertOk()
        ->assertJsonPath('data.report.currency', 'NGN')
        ->assertJsonPath('data.report.total_debit_minor_units', 2500)
        ->assertJsonPath('data.report.closing_balance_minor_units', -2500)
        ->assertJsonPath('data.report.transactions.0.transaction_type', 'ledger_adjustment')
        ->assertJsonPath('data.report.transactions.0.balance_after_minor_units', -2500);
});

it('validates report periods and returns not found reports safely', function (): void {
    allowReportingApi();
    Sanctum::actingAs(reportingApiUser());
    $missing = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    $this->getJson('/api/v1/reports/ledger?from=2026-09-30&to=2026-09-01&per_page=501')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'per_page']);
    $this->getJson("/api/v1/reports/customers/{$missing}")->assertNotFound();
    $this->getJson("/api/v1/reports/accounts/{$missing}/transactions?from=2026-09-01&to=2026-09-30")
        ->assertNotFound();
});
