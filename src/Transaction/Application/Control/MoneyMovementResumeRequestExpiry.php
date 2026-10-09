<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

interface MoneyMovementResumeRequestExpiry
{
    /** Returns how many elapsed or superseded requests were closed. */
    public function expire(): int;
}
