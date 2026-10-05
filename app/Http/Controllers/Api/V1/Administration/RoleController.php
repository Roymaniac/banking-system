<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Role\AssignRoleToStaff;
use Administration\Application\Role\CreateRole;
use Administration\Application\Role\DeactivateRole;
use Administration\Domain\Role\Exception\InactiveRoleCannotChange;
use Administration\Domain\Role\Exception\RoleAlreadyAssigned;
use Administration\Domain\Role\Exception\RoleNameAlreadyExists;
use Administration\Domain\Role\Exception\RoleNotFound;
use Administration\Domain\Role\ValueObject\RoleId;
use Administration\Domain\Staff\Exception\InactiveStaffCannotChange;
use Administration\Domain\Staff\Exception\StaffNotFound;
use Administration\Domain\Staff\ValueObject\StaffId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\CreateRoleRequest;
use App\Http\Resources\Api\V1\Administration\RoleResource;
use Illuminate\Http\JsonResponse;

/** Exposes role lifecycle and staff-assignment commands to authorized administrators. */
final class RoleController extends Controller
{
    public function store(CreateRoleRequest $request, CreateRole $create): JsonResponse
    {
        try {
            $role = $create->handle(
                $request->string('name')->toString(),
                $request->string('label')->toString(),
            );
        } catch (RoleNameAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => ['role' => new RoleResource($role)],
        ], 201);
    }

    public function deactivate(string $role, DeactivateRole $deactivate): JsonResponse
    {
        try {
            $deactivate->handle(new RoleId($role));
        } catch (RoleNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveRoleCannotChange $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Role deactivated successfully.']);
    }

    public function assignStaff(
        string $role,
        string $staff,
        AssignRoleToStaff $assign,
    ): JsonResponse {
        try {
            $assign->handle(new RoleId($role), new StaffId($staff));
        } catch (RoleNotFound|StaffNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveRoleCannotChange|InactiveStaffCannotChange|RoleAlreadyAssigned $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Role assigned to staff successfully.']);
    }
}
