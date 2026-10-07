<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Administration;

use Administration\Domain\Department\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
final class DepartmentResource extends JsonResource
{
    /** @return array<string, string|null> */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->id()->value(),
            'code' => $department->code()->value,
            'name' => $department->name()->value,
            'status' => $department->status()->value,
            'created_at' => $department->createdAt()->format(DATE_ATOM),
            'deactivated_at' => $department->deactivatedAt()?->format(DATE_ATOM),
        ];
    }
}
