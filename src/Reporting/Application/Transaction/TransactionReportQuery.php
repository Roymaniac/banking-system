<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use Reporting\Application\Transaction\View\TransactionReportView;

interface TransactionReportQuery
{
    /** Returns null only when the account or its ledger does not exist. */
    public function find(
        AccountId $accountId,
        TransactionReportPeriod $period
    ): ?TransactionReportView;
}
