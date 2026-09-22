<?php

declare(strict_types=1);

namespace Transaction\Application\MultipleTransfer;

use Account\Domain\Account\ValueObject\AccountId;

/** Describes one recipient and the amount they should receive in minor units. */
final readonly class TransferRecipient
{
    public function __construct(
        public AccountId $accountId,
        public int $minorUnits,
    ) {}
}
