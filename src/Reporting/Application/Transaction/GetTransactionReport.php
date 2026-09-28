<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use Reporting\Application\Transaction\Exception\TransactionReportNotFound;
use Reporting\Application\Transaction\View\TransactionReportView;

/** Retrieves a posted transaction statement for one account and period. */
final readonly class GetTransactionReport
{
    public function __construct(
        private TransactionReportQuery $reports,
    ) {}

    public function handle(
        AccountId $accountId,
        TransactionReportPeriod $period
    ): TransactionReportView {

        return $this->reports->find($accountId, $period)
            ?? throw TransactionReportNotFound::forAccount($accountId);
    }
}
