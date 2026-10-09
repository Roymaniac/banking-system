<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

interface MoneyMovementResumeRequestExpiry
{
    /** Returns how many stale requests were marked expired. */
    public function expire(): int;
}
