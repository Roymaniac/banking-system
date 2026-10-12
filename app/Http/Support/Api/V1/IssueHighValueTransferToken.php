<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use App\Models\User;
use DateInterval;
use Identity\Application\Security\SecurityAlert;
use Identity\Application\Security\SecurityAlertNotifier;
use Identity\Domain\User\ValueObject\EmailAddress;
use Laravel\Sanctum\NewAccessToken;
use Shared\Contracts\Clock;
use Shared\Contracts\TransactionManager;

/** Issues a brief token that approves one precisely fingerprinted transfer. */
final readonly class IssueHighValueTransferToken
{
    public function __construct(
        private Clock $clock,
        private HighValueTransferApproval $approval,
        private SecurityAlertNotifier $alerts,
        private TransactionManager $transactions,
    ) {}

    public function issue(User $user, string $fingerprint): NewAccessToken
    {
        $expiresAt = $this->clock->now()->add(new DateInterval(sprintf(
            'PT%dM',
            max(1, (int) config('security.high_value_transfer.approval_minutes', 5)),
        )));

        return $this->transactions->run(function () use ($user, $fingerprint, $expiresAt): NewAccessToken {
            $token = $user->createToken(
                'High-value transfer approval',
                ['customer', $this->approval->ability($fingerprint)],
                $expiresAt,
            );
            $this->alerts->send(
                new EmailAddress($user->email),
                SecurityAlert::HighValueTransferApproved,
                $this->clock->now(),
            );

            return $token;
        });
    }
}
