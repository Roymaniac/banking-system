<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Administration;

use Administration\Domain\Permission\PermissionDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PermissionDefinition */
final class PermissionResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        /** @var PermissionDefinition $permission */
        $permission = $this->resource;

        return [
            'id' => $permission->id()->value(),
            'name' => $permission->name()->value,
            'label' => $permission->label()->value,
            'created_at' => $permission->createdAt()->format(DATE_ATOM),
        ];
    }
}
