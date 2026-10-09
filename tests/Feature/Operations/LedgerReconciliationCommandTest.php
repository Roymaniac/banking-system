<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ledger\Application\Reconciliation\LedgerReconciliation;
use Ledger\Infrastructure\Reconciliation\DatabaseLedgerReconciliation;
use Shared\Domain\Identifier\Uuid;

uses(RefreshDatabase::class);

/** @return array{entry: string, debit_ledger: string, credit_ledger: string} */
function createReconciledJournalEntry(): array
{
    $ledgerIds = [];

    foreach (['1000000101', '1000000102'] as $accountNumber) {
        $customerId = CustomerId::generate();
        $accountId = AccountId::generate();
        $ledgerId = Uuid::generate();

        DB::table('customers')->insert([
            'id' => $customerId->value(),
            'user_id' => Uuid::generate()->value(),
            'first_name' => 'Reconciliation',
            'middle_name' => null,
            'last_name' => 'Owner',
            'date_of_birth' => '1990-01-01',
            'registered_at' => '2026-10-01 00:00:00',
            'version' => 1,
        ]);
        DB::table('accounts')->insert([
            'id' => $accountId->value(),
            'customer_id' => $customerId->value(),
            'number' => $accountNumber,
            'type' => 'savings',
            'currency' => 'NGN',
            'status' => 'active',
            'created_at' => '2026-10-01 00:00:00',
            'version' => 1,
        ]);
        DB::table('ledgers')->insert([
            'id' => $ledgerId->value(),
            'account_id' => $accountId->value(),
            'currency' => 'NGN',
            'created_at' => '2026-10-01 00:00:00',
            'version' => 1,
        ]);

        $ledgerIds[] = $ledgerId->value();
    }

    $entryId = Uuid::generate()->value();
    DB::table('ledger_entries')->insert([
        'id' => $entryId,
        'ledger_id' => $ledgerIds[0],
        'reference' => 'RECONCILIATION-001',
        'description' => 'Reconciliation fixture',
        'occurred_at' => '2026-10-01 01:00:00',
        'recorded_at' => '2026-10-01 01:00:00',
        'status' => 'posted',
        'version' => 1,
    ]);

    foreach ([[$ledgerIds[0], 'debit'], [$ledgerIds[1], 'credit']] as [$ledgerId, $side]) {
        DB::table('ledger_postings')->insert([
            'id' => Uuid::generate()->value(),
            'entry_id' => $entryId,
            'ledger_id' => $ledgerId,
            'side' => $side,
            'minor_units' => 1000,
            'currency' => 'NGN',
        ]);
        DB::table('ledger_balance_contributions')->insert([
            'entry_id' => $entryId,
            'ledger_id' => $ledgerId,
            'side' => $side,
            'minor_units' => 1000,
            'projected_at' => '2026-10-01 01:00:00',
        ]);
        DB::table('ledger_balances')->insert([
            'ledger_id' => $ledgerId,
            'currency' => 'NGN',
            'debit_minor_units' => $side === 'debit' ? 1000 : 0,
            'credit_minor_units' => $side === 'credit' ? 1000 : 0,
            'balance_minor_units' => $side === 'debit' ? -1000 : 1000,
            'updated_at' => '2026-10-01 01:00:00',
        ]);
    }

    return ['entry' => $entryId, 'debit_ledger' => $ledgerIds[0], 'credit_ledger' => $ledgerIds[1]];
}

it('binds reconciliation to its database implementation', function (): void {
    expect(app(LedgerReconciliation::class))->toBeInstanceOf(DatabaseLedgerReconciliation::class);
});

it('passes when journal entries, contributions, and balances agree', function (): void {
    createReconciledJournalEntry();

    $report = app(LedgerReconciliation::class)->inspect();

    expect($report->isReconciled())->toBeTrue()
        ->and($report->unbalancedPostedEntries)->toBe(0)
        ->and($report->contributionMismatches)->toBe(0)
        ->and($report->balanceMismatches)->toBe(0);

    $this->artisan('banking:reconcile-ledger')
        ->expectsOutputToContain('Ledger reconciliation passed')
        ->assertSuccessful();

    $this->assertDatabaseHas('ledger_reconciliation_statuses', [
        'name' => 'ledger',
        'status' => 'healthy',
        'unbalanced_posted_entries' => 0,
        'contribution_mismatches' => 0,
        'balance_mismatches' => 0,
    ]);
});

it('fails when journal and projection records have drifted', function (): void {
    $records = createReconciledJournalEntry();
    DB::table('ledger_postings')
        ->where('entry_id', $records['entry'])
        ->where('side', 'credit')
        ->update(['minor_units' => 900]);

    $report = app(LedgerReconciliation::class)->inspect();

    expect($report->isReconciled())->toBeFalse()
        ->and($report->unbalancedPostedEntries)->toBe(1)
        ->and($report->contributionMismatches)->toBe(1)
        ->and($report->balanceMismatches)->toBe(1);

    $this->artisan('banking:reconcile-ledger')
        ->expectsOutputToContain('Ledger reconciliation failed')
        ->assertFailed();

    $this->assertDatabaseHas('ledger_reconciliation_statuses', [
        'name' => 'ledger',
        'status' => 'unhealthy',
        'unbalanced_posted_entries' => 1,
        'contribution_mismatches' => 1,
        'balance_mismatches' => 1,
    ]);
    $this->assertDatabaseHas('money_movement_controls', [
        'name' => 'global',
        'enabled' => false,
        'source' => 'reconciliation',
    ]);
    $this->assertDatabaseHas('money_movement_control_events', [
        'action' => 'suspended',
        'source' => 'reconciliation',
    ]);
});

it('detects a missing projection contribution and balance', function (): void {
    $records = createReconciledJournalEntry();
    DB::table('ledger_balance_contributions')->where('ledger_id', $records['credit_ledger'])->delete();
    DB::table('ledger_balances')->where('ledger_id', $records['credit_ledger'])->delete();

    $report = app(LedgerReconciliation::class)->inspect();

    expect($report->unbalancedPostedEntries)->toBe(0)
        ->and($report->contributionMismatches)->toBe(1)
        ->and($report->balanceMismatches)->toBe(1);
});

it('detects a posted entry that has no journal lines', function (): void {
    $records = createReconciledJournalEntry();
    DB::table('ledger_postings')->where('entry_id', $records['entry'])->delete();
    DB::table('ledger_balance_contributions')->where('entry_id', $records['entry'])->delete();
    DB::table('ledger_balances')->delete();

    $report = app(LedgerReconciliation::class)->inspect();

    expect($report->unbalancedPostedEntries)->toBe(1)
        ->and($report->contributionMismatches)->toBe(0)
        ->and($report->balanceMismatches)->toBe(0);
});
