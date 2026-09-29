<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Reporting\Application\Transaction\TransactionReportPeriod;
use Reporting\Application\Transaction\TransactionReportQuery;
use Reporting\Infrastructure\Persistence\DatabaseTransactionReportQuery;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTransactionReportAccount(): array
{
    $customerId = CustomerId::generate();
    $accountId = AccountId::generate();
    $ledgerId = Uuid::generate();
    DB::table('customers')->insert([
        'id' => $customerId->value(),
        'user_id' => Uuid::generate()->value(),
        'first_name' => 'Report',
        'middle_name' => null,
        'last_name' => 'Owner',
        'date_of_birth' => '1990-01-01',
        'registered_at' => '2026-08-01 00:00:00',
        'version' => 1,
    ]);
    DB::table('accounts')->insert([
        'id' => $accountId->value(),
        'customer_id' => $customerId->value(),
        'number' => '1000000001',
        'type' => 'savings',
        'currency' => 'NGN',
        'status' => 'active',
        'created_at' => '2026-08-01 00:00:00',
        'version' => 1,
    ]);
    DB::table('ledgers')->insert([
        'id' => $ledgerId->value(),
        'account_id' => $accountId->value(),
        'currency' => 'NGN',
        'created_at' => '2026-08-01 00:00:00',
        'version' => 1,
    ]);

    return [$accountId, $ledgerId];
}

function addTransactionReportEntry(
    Uuid $ledgerId,
    string $reference,
    string $date,
    string $side,
    int $amount,
    string $status = 'posted'
): Uuid {

    $entryId = Uuid::generate();
    DB::table('ledger_entries')->insert([
        'id' => $entryId->value(),
        'ledger_id' => $ledgerId->value(),
        'reference' => $reference,
        'description' => "Report entry {$reference}",
        'occurred_at' => $date,
        'recorded_at' => $date,
        'status' => $status,
        'version' => 1,
    ]);
    DB::table('ledger_postings')->insert([
        'id' => Uuid::generate()->value(),
        'entry_id' => $entryId->value(),
        'ledger_id' => $ledgerId->value(),
        'side' => $side,
        'minor_units' => $amount,
        'currency' => 'NGN',
    ]);

    return $entryId;
}

it('binds transaction reporting to its database query', function (): void {
    expect(app(TransactionReportQuery::class))
        ->toBeInstanceOf(DatabaseTransactionReportQuery::class);
});

it('calculates opening, running, period, and closing balances from posted entries', function (): void {
    [$accountId, $ledgerId] = createTransactionReportAccount();
    $openingEntry = addTransactionReportEntry(
        $ledgerId,
        'OPENING',
        '2026-08-31 12:00:00',
        'credit',
        10000
    );

    $withdrawalEntry = addTransactionReportEntry(
        $ledgerId,
        'WITHDRAWAL',
        '2026-09-05 12:00:00',
        'debit',
        2500
    );

    $depositEntry = addTransactionReportEntry(
        $ledgerId,
        'DEPOSIT',
        '2026-09-06 12:00:00',
        'credit',
        1000
    );

    addTransactionReportEntry(
        $ledgerId,
        'DRAFT',
        '2026-09-07 12:00:00',
        'debit',
        9000,
        'draft'
    );

    DB::table('deposits')->insert([
        'id' => Uuid::generate()->value(),
        'account_id' => $accountId->value(),
        'ledger_entry_id' => $openingEntry->value(),
        'reference' => 'OPENING',
        'minor_units' => 10000,
        'currency' => 'NGN',
        'completed_at' => '2026-08-31 12:00:00',
        'version' => 1,
    ]);
    DB::table('withdrawals')->insert([
        'id' => Uuid::generate()->value(),
        'account_id' => $accountId->value(),
        'ledger_entry_id' => $withdrawalEntry->value(),
        'reference' => 'WITHDRAWAL',
        'minor_units' => 2500,
        'currency' => 'NGN',
        'completed_at' => '2026-09-05 12:00:00',
        'version' => 1,
    ]);
    DB::table('deposits')->insert([
        'id' => Uuid::generate()->value(),
        'account_id' => $accountId->value(),
        'ledger_entry_id' => $depositEntry->value(),
        'reference' => 'DEPOSIT',
        'minor_units' => 1000,
        'currency' => 'NGN',
        'completed_at' => '2026-09-06 12:00:00',
        'version' => 1,
    ]);
    $period = new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        new DateTimeImmutable('2026-09-30T23:59:59+00:00'),
        1,
        1,
    );

    $report = app(TransactionReportQuery::class)->find($accountId, $period);

    expect($report)->not->toBeNull()
        ->and($report->openingBalanceMinorUnits)->toBe(10000)
        ->and($report->totalDebitMinorUnits)->toBe(2500)
        ->and($report->totalCreditMinorUnits)->toBe(1000)
        ->and($report->closingBalanceMinorUnits)->toBe(8500)
        ->and($report->totalTransactions)->toBe(2)
        ->and($report->transactionCount())->toBe(1)
        ->and($report->hasNextPage())->toBeTrue()
        ->and($report->transactions[0]->transactionType)->toBe('withdrawal')
        ->and($report->transactions[0]->balanceAfterMinorUnits)->toBe(7500);

    $secondPage = app(TransactionReportQuery::class)->find(
        $accountId,
        new TransactionReportPeriod(
            $period->from,
            $period->to,
            2,
            1
        ),
    );

    expect($secondPage->transactions[0]->transactionType)->toBe('deposit')
        ->and($secondPage->transactions[0]->balanceAfterMinorUnits)->toBe(8500)
        ->and($secondPage->hasNextPage())->toBeFalse();
});

it('returns null when the requested account has no ledger', function (): void {
    $period = new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-30')
    );

    expect(app(TransactionReportQuery::class)
        ->find(AccountId::generate(), $period))
        ->toBeNull();
});
