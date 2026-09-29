<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger;

use Reporting\Application\Ledger\View\LedgerReportView;

/** Retrieves one page of the bank's general journal with reconciliation totals. */
final readonly class GetLedgerReport
{
    public function __construct(
        private LedgerReportQuery $reports,
    ) {}

    public function handle(LedgerReportRequest $request): LedgerReportView
    {
        return $this->reports->generate($request);
    }
}
