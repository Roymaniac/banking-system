<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

use Account\Domain\Account\ValueObject\AccountId;

/** Records blocked risk decisions without exposing transaction instructions. */
interface TransferRiskMonitor
{
    public function velocityBlocked(
        AccountId $senderAccountId,
        int $windowMinutes,
        int $maximumTransfers,
    ): void;
}
