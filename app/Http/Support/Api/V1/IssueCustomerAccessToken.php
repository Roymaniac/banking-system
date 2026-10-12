<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use App\Models\User;
use DateInterval;
use Laravel\Sanctum\NewAccessToken;
use Shared\Contracts\Clock;

/** Issues the ordinary customer token used after all login factors pass. */
final readonly class IssueCustomerAccessToken
{
    public function __construct(private Clock $clock) {}

    public function issue(User $user, string $deviceName): NewAccessToken
    {
        $expiresAt = $this->clock->now()->add(new DateInterval(sprintf(
            'PT%dM',
            max(1, (int) config('sanctum.expiration', 1440)),
        )));

        return $user->createToken($deviceName, ['customer'], $expiresAt);
    }
}
