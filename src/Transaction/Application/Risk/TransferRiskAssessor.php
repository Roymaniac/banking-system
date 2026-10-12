<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;

/** Checks recent account behaviour before an outgoing transfer is accepted. */
interface TransferRiskAssessor
{
    public function assertAllowed(AccountId $senderAccountId, DateTimeImmutable $assessedAt): void;
}
