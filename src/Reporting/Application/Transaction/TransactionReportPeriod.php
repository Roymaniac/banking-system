<?php

declare(strict_types=1);

namespace Reporting\Application\Transaction;

use DateTimeImmutable;
use InvalidArgumentException;

/** Defines the inclusive time window covered by a transaction report. */
final readonly class TransactionReportPeriod
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('The report start must not be later than its end.');
        }
    }
}
