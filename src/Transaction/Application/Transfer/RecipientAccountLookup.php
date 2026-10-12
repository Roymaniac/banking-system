<?php

declare(strict_types=1);

namespace Transaction\Application\Transfer;

use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Transaction\Application\Transfer\View\RecipientAccountView;

/** Finds a transfer recipient without exposing their private customer profile. */
interface RecipientAccountLookup
{
    public function findCompatible(
        AccountNumber $number,
        CurrencyCode $currency,
    ): ?RecipientAccountView;
}
