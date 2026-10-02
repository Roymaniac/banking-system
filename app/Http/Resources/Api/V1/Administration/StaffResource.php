<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Administration;

use Administration\Domain\Staff\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StaffResource extends JsonResource
{
    /** @return array<string, string|null> */
    public function toArray(Request $request): array
    {
        /** @var Staff $staff */
        $staff = $this->resource;

        return [
            'id' => $staff->id()->value(),
            'user_id' => $staff->userId()->value(),
            'employee_number' => $staff->employeeNumber()->value,
            'department_id' => $staff->departmentId()->value(),
            'job_title' => $staff->jobTitle()->value,
            'status' => $staff->status()->value,
            'hired_at' => $staff->hiredAt()->format(DATE_ATOM),
            'deactivated_at' => $staff->deactivatedAt()?->format(DATE_ATOM),
        ];
    }
}
