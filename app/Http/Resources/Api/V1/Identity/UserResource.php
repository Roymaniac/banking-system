<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Controls which user details are safe to expose through the API.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /** @return array{identity_user_id: string, email: string, email_verified: bool} */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'identity_user_id' => (string) $user->identity_user_id,
            'email' => (string) $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
