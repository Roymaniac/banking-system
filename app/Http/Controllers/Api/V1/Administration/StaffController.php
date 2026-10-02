<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Staff\DeactivateStaff;
use Administration\Application\Staff\HireStaff;
use Administration\Application\Staff\HireStaffCommand;
use Administration\Application\Staff\TransferStaff;
use Administration\Application\Staff\TransferStaffCommand;
use Administration\Domain\Department\ValueObject\DepartmentId;
use Administration\Domain\Staff\Exception\ActiveDepartmentRequired;
use Administration\Domain\Staff\Exception\InactiveStaffCannotChange;
use Administration\Domain\Staff\Exception\StaffAlreadyExists;
use Administration\Domain\Staff\Exception\StaffNotFound;
use Administration\Domain\Staff\ValueObject\StaffId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\HireStaffRequest;
use App\Http\Requests\Api\V1\Administration\TransferStaffRequest;
use App\Http\Resources\Api\V1\Administration\StaffResource;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\JsonResponse;

/** Runs permission-protected staff lifecycle commands. */
final class StaffController extends Controller
{
    public function store(HireStaffRequest $request, HireStaff $hire): JsonResponse
    {
        try {
            $staff = $hire->handle(
                new HireStaffCommand(
                    new UserId($request->string('user_id')->toString()),
                    $request->string('employee_number')->toString(),
                    new DepartmentId($request->string('department_id')->toString()),
                    $request->string('job_title')->toString(),
                )
            );
        } catch (ActiveDepartmentRequired $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (StaffAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'staff' => new StaffResource($staff)
            ]
        ], 201);
    }

    public function transfer(
        TransferStaffRequest $request,
        string $staff,
        TransferStaff $transfer,
    ): JsonResponse {
        try {
            $transfer->handle(
                new TransferStaffCommand(
                    new StaffId($staff),
                    new DepartmentId($request->string('department_id')->toString()),
                )
            );
        } catch (StaffNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (ActiveDepartmentRequired $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (InactiveStaffCannotChange $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Staff member transferred successfully.']);
    }

    public function deactivate(string $staff, DeactivateStaff $deactivate): JsonResponse
    {
        try {
            $deactivate->handle(new StaffId($staff));
        } catch (StaffNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveStaffCannotChange $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Staff member deactivated successfully.']);
    }
}
