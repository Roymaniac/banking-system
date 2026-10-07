<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Administration;

use Administration\Domain\Role\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /** @return array<string, string|null> */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id()->value(),
            'name' => $role->name()->value,
            'label' => $role->label()->value,
            'status' => $role->status()->value,
            'created_at' => $role->createdAt()->format(DATE_ATOM),
            'deactivated_at' => $role->deactivatedAt()?->format(DATE_ATOM),
        ];
    }
}
