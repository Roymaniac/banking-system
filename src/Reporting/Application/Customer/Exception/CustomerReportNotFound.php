<?php

declare(strict_types=1);

namespace Reporting\Application\Customer\Exception;

use Customer\Domain\Customer\ValueObject\CustomerId;
use RuntimeException;

final class CustomerReportNotFound extends RuntimeException
{
    public static function forCustomer(CustomerId $customerId): self
    {
        return new self(sprintf('No report is available for customer %s.', $customerId->value()));
    }
}
