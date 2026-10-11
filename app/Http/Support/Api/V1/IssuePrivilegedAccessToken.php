<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use Administration\Application\Security\StaffAccessChecker;
use App\Models\User;
use DateInterval;
use Identity\Application\Authentication\AuthenticateUser;
use Identity\Application\Authentication\AuthenticateUserCommand;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Identity\Domain\User\ValueObject\UserId;
use Laravel\Sanctum\NewAccessToken;
use Shared\Contracts\Clock;

/** Rechecks an active staff member before issuing a short privileged session. */
final readonly class IssuePrivilegedAccessToken
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private StaffAccessChecker $staff,
        private SecurityMonitor $security,
        private Clock $clock,
    ) {}

    public function issue(User $user, string $password, string $deviceName): NewAccessToken
    {
        $identity = $this->authenticate->handle(new AuthenticateUserCommand($user->email, $password));
        $userId = new UserId($identity->id()->value());

        if (! $this->staff->isActive($userId)) {
            $this->security->privilegedAccessDenied($userId);

            throw AccessDenied::create();
        }

        $expiresAt = $this->clock->now()->add(new DateInterval(sprintf(
            'PT%dM',
            max(1, (int) config('security.privileged_access.minutes', 15)),
        )));
        $token = $user->createToken(
            'Privileged: '.$deviceName,
            ['customer', 'privileged'],
            $expiresAt,
        );

        $this->security->privilegedAccessGranted($userId);

        return $token;
    }
}
