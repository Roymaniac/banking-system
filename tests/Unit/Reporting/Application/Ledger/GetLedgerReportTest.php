<?php

declare(strict_types=1);

use Reporting\Application\Ledger\GetLedgerReport;
use Reporting\Application\Ledger\LedgerReportQuery;
use Reporting\Application\Ledger\LedgerReportRequest;
use Reporting\Application\Ledger\View\LedgerReportView;

it('returns the generated general ledger report', function (): void {
    $request = new LedgerReportRequest(
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-30'),
        2,
        50,
    );
    $view = new LedgerReportView($request, 60, 2, 0, [], []);
    $query = Mockery::mock(LedgerReportQuery::class);
    $query->shouldReceive('generate')->once()->with($request)->andReturn($view);

    $report = (new GetLedgerReport($query))->handle($request);

    expect($report)->toBe($view)
        ->and($report->hasNextPage())->toBeFalse();
});

it('rejects invalid report pagination', function (int $page, int $perPage): void {
    new LedgerReportRequest(
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-30'),
        $page,
        $perPage,
    );
})->with([
    'zero page' => [0, 100],
    'zero page size' => [1, 0],
    'excessive page size' => [1, 501],
])->throws(InvalidArgumentException::class);
