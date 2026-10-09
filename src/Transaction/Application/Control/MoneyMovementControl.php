<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

interface MoneyMovementControl
{
    /** Locks and verifies the switch inside the caller's financial transaction. */
    public function assertEnabled(): void;

    public function suspend(string $reason, string $source): void;

    public function resume(string $reason, string $source): void;
}
