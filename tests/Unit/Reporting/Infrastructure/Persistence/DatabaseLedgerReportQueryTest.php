<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Reporting\Application\Ledger\LedgerReportQuery;
use Reporting\Application\Ledger\LedgerReportRequest;
use Reporting\Infrastructure\Persistence\DatabaseLedgerReportQuery;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createLedgerReportLedger(string $number): Uuid
{
    $customerId = CustomerId::generate();
    $accountId = AccountId::generate();
    $ledgerId = Uuid::generate();
    DB::table('customers')->insert([
        'id' => $customerId->value(), 'user_id' => Uuid::generate()->value(),
        'first_name' => 'Ledger', 'middle_name' => null, 'last_name' => 'Owner',
        'date_of_birth' => '1990-01-01', 'registered_at' => '2026-09-01 00:00:00', 'version' => 1,
    ]);
    DB::table('accounts')->insert([
        'id' => $accountId->value(), 'customer_id' => $customerId->value(), 'number' => $number,
        'type' => 'savings', 'currency' => 'NGN', 'status' => 'active',
        'created_at' => '2026-09-01 00:00:00', 'version' => 1,
    ]);
    DB::table('ledgers')->insert([
        'id' => $ledgerId->value(), 'account_id' => $accountId->value(), 'currency' => 'NGN',
        'created_at' => '2026-09-01 00:00:00', 'version' => 1,
    ]);

    return $ledgerId;
}

function addLedgerReportEntry(Uuid $origin, Uuid $debitLedger, Uuid $creditLedger, string $reference, int $debit, int $credit, string $occurredAt, string $status = 'posted'): void
{
    $entryId = Uuid::generate();
    DB::table('ledger_entries')->insert([
        'id' => $entryId->value(), 'ledger_id' => $origin->value(), 'reference' => $reference,
        'description' => "Journal {$reference}", 'occurred_at' => $occurredAt,
        'recorded_at' => $occurredAt, 'status' => $status, 'version' => 1,
    ]);
    DB::table('ledger_postings')->insert([
        [
            'id' => Uuid::generate()->value(), 'entry_id' => $entryId->value(),
            'ledger_id' => $debitLedger->value(), 'side' => 'debit', 'minor_units' => $debit, 'currency' => 'NGN',
        ],
        [
            'id' => Uuid::generate()->value(), 'entry_id' => $entryId->value(),
            'ledger_id' => $creditLedger->value(), 'side' => 'credit', 'minor_units' => $credit, 'currency' => 'NGN',
        ],
    ]);
}

it('binds ledger reporting to its database query', function (): void {
    expect(app(LedgerReportQuery::class))->toBeInstanceOf(DatabaseLedgerReportQuery::class);
});

it('reports journal legs, currency totals, drafts, and reconciliation problems', function (): void {
    $firstLedger = createLedgerReportLedger('1000000001');
    $secondLedger = createLedgerReportLedger('1000000002');
    addLedgerReportEntry($firstLedger, $firstLedger, $secondLedger, 'BALANCED', 5000, 5000, '2026-09-15 10:00:00');
    addLedgerReportEntry($firstLedger, $firstLedger, $secondLedger, 'BROKEN', 1000, 900, '2026-09-15 11:00:00');
    addLedgerReportEntry($firstLedger, $firstLedger, $secondLedger, 'DRAFT', 200, 200, '2026-09-15 12:00:00', 'draft');
    $request = new LedgerReportRequest(
        new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        new DateTimeImmutable('2026-09-30T23:59:59+00:00'),
        1,
        1,
    );

    $report = app(LedgerReportQuery::class)->generate($request);

    expect($report->totalPostedEntries)->toBe(2)
        ->and($report->draftEntryCount)->toBe(1)
        ->and($report->unbalancedPostedEntryCount)->toBe(1)
        ->and($report->entries)->toHaveCount(1)
        ->and($report->entries[0]->reference)->toBe('BALANCED')
        ->and($report->entries[0]->postings)->toHaveCount(2)
        ->and($report->entries[0]->isBalanced())->toBeTrue()
        ->and($report->currencySummaries)->toHaveCount(1)
        ->and($report->currencySummaries[0]->totalDebitMinorUnits)->toBe(6000)
        ->and($report->currencySummaries[0]->totalCreditMinorUnits)->toBe(5900)
        ->and($report->currencySummaries[0]->differenceMinorUnits())->toBe(-100)
        ->and($report->hasNextPage())->toBeTrue();
});

it('returns an empty report when the period has no ledger entries', function (): void {
    $request = new LedgerReportRequest(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-09-30'));

    $report = app(LedgerReportQuery::class)->generate($request);

    expect($report->totalPostedEntries)->toBe(0)
        ->and($report->entries)->toBeEmpty()
        ->and($report->currencySummaries)->toBeEmpty();
});
