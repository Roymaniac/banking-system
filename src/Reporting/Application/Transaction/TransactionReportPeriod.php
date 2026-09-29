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
        public int $page = 1,
        public int $perPage = 100,
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('The report start must not be later than its end.');
        }

        if ($page < 1) {
            throw new InvalidArgumentException('The report page must be at least 1.');
        }

        if ($perPage < 1 || $perPage > 500) {
            throw new InvalidArgumentException('The report page size must be between 1 and 500.');
        }
    }
}
