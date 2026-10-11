<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use Identity\Application\Session\View\UserSessionView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shows recognizable device information without exposing the token hash.
 *
 * @mixin UserSessionView
 */
final class UserSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_name' => $this->deviceName,
            'abilities' => $this->abilities,
            'is_current' => $this->isCurrent,
            'last_used_at' => $this->lastUsedAt?->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
