<?php

declare(strict_types=1);

namespace Reporting\Application\Customer;

use Customer\Domain\Customer\ValueObject\CustomerId;
use Reporting\Application\Customer\Exception\CustomerReportNotFound;
use Reporting\Application\Customer\View\CustomerReportView;

/** Retrieves one complete customer overview from the reporting data source. */
final readonly class GetCustomerReport
{
    public function __construct(
        private CustomerReportQuery $reports,
    ) {}

    public function handle(CustomerId $customerId): CustomerReportView
    {
        return $this->reports->find($customerId)
            ?? throw CustomerReportNotFound::forCustomer($customerId);
    }
}
