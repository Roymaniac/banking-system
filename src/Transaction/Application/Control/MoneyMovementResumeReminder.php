<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

interface MoneyMovementResumeReminder
{
    /** Returns the number of pending requests whose reminder was recorded. */
    public function sendDue(): int;
}
