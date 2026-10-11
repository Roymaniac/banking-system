<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\Request;

/** Reads trusted Identity and session IDs from the authenticated request. */
final class CurrentIdentity
{
    public function userId(Request $request): UserId
    {
        /** @var User $user */
        $user = $request->user();

        return new UserId((string) $user->identity_user_id);
    }

    public function sessionId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return (int) $user->currentAccessToken()->getKey();
    }
}
