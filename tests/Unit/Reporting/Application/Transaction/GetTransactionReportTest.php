<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Reporting\Application\Transaction\Exception\TransactionReportNotFound;
use Reporting\Application\Transaction\GetTransactionReport;
use Reporting\Application\Transaction\TransactionReportPeriod;
use Reporting\Application\Transaction\TransactionReportQuery;
use Reporting\Application\Transaction\View\TransactionReportView;

it('returns the statement supplied by the transaction report query', function (): void {
    $accountId = AccountId::generate();
    $period = new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-01T00:00:00+01:00'),
        new DateTimeImmutable('2026-09-30T23:59:59+01:00'),
    );
    $view = new TransactionReportView(
        $accountId->value(),
        '1000000001',
        'NGN',
        $period,
        1000,
        200,
        500,
        1300,
        0,
        [],
    );
    $query = Mockery::mock(TransactionReportQuery::class);
    $query->shouldReceive('find')->once()->with($accountId, $period)->andReturn($view);

    expect((new GetTransactionReport($query))->handle($accountId, $period))->toBe($view);
});

it('rejects a period whose end is before its start', function (): void {
    new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-30'),
        new DateTimeImmutable('2026-09-01'),
    );
})->throws(InvalidArgumentException::class);

it('rejects unsafe transaction report pagination', function (int $page, int $perPage): void {
    new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-30'),
        $page,
        $perPage,
    );
})->with([
    'zero page' => [0, 100],
    'zero size' => [1, 0],
    'excessive size' => [1, 501],
])->throws(InvalidArgumentException::class);

it('reports clearly when an account has no ledger report', function (): void {
    $accountId = AccountId::generate();
    $period = new TransactionReportPeriod(
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-30')
    );
    $query = Mockery::mock(TransactionReportQuery::class);
    $query->shouldReceive('find')->once()->andReturnNull();

    (new GetTransactionReport($query))->handle($accountId, $period);
})->throws(TransactionReportNotFound::class);
