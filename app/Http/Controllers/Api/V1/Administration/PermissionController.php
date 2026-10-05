<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Permission\CreatePermission;
use Administration\Application\Permission\GrantPermissionToRole;
use Administration\Application\Permission\RevokePermissionFromRole;
use Administration\Domain\Permission\Exception\PermissionAlreadyGranted;
use Administration\Domain\Permission\Exception\PermissionNameAlreadyExists;
use Administration\Domain\Permission\Exception\PermissionNotFound;
use Administration\Domain\Permission\Exception\PermissionNotGranted;
use Administration\Domain\Permission\ValueObject\PermissionId;
use Administration\Domain\Role\Exception\InactiveRoleCannotChange;
use Administration\Domain\Role\Exception\RoleNotFound;
use Administration\Domain\Role\ValueObject\RoleId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\CreatePermissionRequest;
use App\Http\Resources\Api\V1\Administration\PermissionResource;
use Illuminate\Http\JsonResponse;

/** Manages the permission catalogue and the permissions granted to each role. */
final class PermissionController extends Controller
{
    public function store(
        CreatePermissionRequest $request,
        CreatePermission $create
    ): JsonResponse {
        try {
            $permission = $create->handle(
                $request->string('name')->toString(),
                $request->string('label')->toString(),
            );
        } catch (PermissionNameAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => ['permission' => new PermissionResource($permission)],
        ], 201);
    }

    public function grant(
        string $role,
        string $permission,
        GrantPermissionToRole $grant,
    ): JsonResponse {
        try {
            $grant->handle(new RoleId($role), new PermissionId($permission));
        } catch (RoleNotFound|PermissionNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveRoleCannotChange|PermissionAlreadyGranted $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Permission granted to role successfully.']);
    }

    public function revoke(
        string $role,
        string $permission,
        RevokePermissionFromRole $revoke,
    ): JsonResponse {
        try {
            $revoke->handle(new RoleId($role), new PermissionId($permission));
        } catch (RoleNotFound|PermissionNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveRoleCannotChange|PermissionNotGranted $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Permission revoked from role successfully.']);
    }
}
