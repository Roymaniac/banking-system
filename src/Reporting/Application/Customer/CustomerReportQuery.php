<?php

declare(strict_types=1);

namespace Reporting\Application\Customer;

use Customer\Domain\Customer\ValueObject\CustomerId;
use Reporting\Application\Customer\View\CustomerReportView;

interface CustomerReportQuery
{
    /** Returns the assembled reporting view, or null when the customer does not exist. */
    public function find(CustomerId $customerId): ?CustomerReportView;
}
