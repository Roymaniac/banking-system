<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Transaction;

use Transaction\Domain\DailyLimit\DailyTransactionLimit;

/** Carries every value needed to build the daily-limit API response. */
final readonly class DailyTransactionLimitView
{
    public function __construct(
        public DailyTransactionLimit $limit,
        public int $usedMinorUnits,
    ) {}
}
