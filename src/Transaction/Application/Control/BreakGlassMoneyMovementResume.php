<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use InvalidArgumentException;
use Shared\Contracts\TransactionManager;
use Transaction\Application\Control\Exception\InvalidMoneyMovementResume;

/** Performs the audited emergency path used only when normal approval is unavailable. */
final readonly class BreakGlassMoneyMovementResume
{
    public function __construct(
        private MoneyMovementControl $control,
        private MoneyMovementSecurityMonitor $securityMonitor,
        private AuthorizationChecker $authorization,
        private TransactionManager $transactions,
    ) {}

    public function handle(UserId $operatorId, string $incidentReference, string $reason): void
    {
        $incidentReference = strtoupper(trim($incidentReference));
        $reason = trim($reason);

        if (preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,49}$/', $incidentReference) !== 1) {
            throw new InvalidArgumentException(
                'The incident reference must contain 3 to 50 uppercase letters, numbers, dots, slashes, underscores, or hyphens.',
            );
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 180) {
            throw new InvalidArgumentException('The emergency reason must contain between 10 and 180 characters.');
        }
        if (! $this->authorization->allows($operatorId, new Permission('money_movement.break_glass'))) {
            throw InvalidMoneyMovementResume::breakGlassNotAuthorized();
        }

        $auditableReason = $incidentReference.': '.$reason;

        $this->transactions->run(function () use ($operatorId, $incidentReference, $auditableReason): void {
            if ($this->control->current()->enabled) {
                throw InvalidMoneyMovementResume::whileEnabled();
            }

            $this->control->resume($auditableReason, 'operator_cli_break_glass', $operatorId);
            $this->securityMonitor->breakGlassResumeUsed($operatorId, $incidentReference);
        });
    }
}
