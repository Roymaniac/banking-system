<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger;

use Reporting\Application\Ledger\View\LedgerReportView;

interface LedgerReportQuery
{
    public function generate(LedgerReportRequest $request): LedgerReportView;
}
