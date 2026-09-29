<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction\Exception;

use Account\Domain\Account\ValueObject\AccountId;
use RuntimeException;

final class TransactionReportNotFound extends RuntimeException
{
    public static function forAccount(AccountId $accountId): self
    {
        return new self(sprintf('No transaction report is available for account %s.', $accountId->value()));
    }
}
