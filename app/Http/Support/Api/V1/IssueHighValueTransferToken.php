<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use App\Models\User;
use DateInterval;
use Laravel\Sanctum\NewAccessToken;
use Shared\Contracts\Clock;

/** Issues a brief token that approves one precisely fingerprinted transfer. */
final readonly class IssueHighValueTransferToken
{
    public function __construct(
        private Clock $clock,
        private HighValueTransferApproval $approval,
    ) {}

    public function issue(User $user, string $fingerprint): NewAccessToken
    {
        $expiresAt = $this->clock->now()->add(new DateInterval(sprintf(
            'PT%dM',
            max(1, (int) config('security.high_value_transfer.approval_minutes', 5)),
        )));

        return $user->createToken(
            'High-value transfer approval',
            ['customer', $this->approval->ability($fingerprint)],
            $expiresAt,
        );
    }
}
