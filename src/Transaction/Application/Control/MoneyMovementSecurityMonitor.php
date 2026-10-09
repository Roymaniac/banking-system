<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use Identity\Domain\User\ValueObject\UserId;

/** Records exceptional money-movement actions without coupling this context to Audit. */
interface MoneyMovementSecurityMonitor
{
    public function breakGlassResumeUsed(UserId $operatorId, string $incidentReference): void;
}
