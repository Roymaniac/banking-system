<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Department\CreateDepartment;
use Administration\Application\Department\CreateDepartmentCommand;
use Administration\Application\Department\DeactivateDepartment;
use Administration\Application\Department\DeactivateDepartmentCommand;
use Administration\Application\Department\RenameDepartment;
use Administration\Application\Department\RenameDepartmentCommand;
use Administration\Domain\Department\Exception\DepartmentCodeAlreadyExists;
use Administration\Domain\Department\Exception\DepartmentNotFound;
use Administration\Domain\Department\Exception\InactiveDepartmentCannotChange;
use Administration\Domain\Department\ValueObject\DepartmentId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\CreateDepartmentRequest;
use App\Http\Requests\Api\V1\Administration\RenameDepartmentRequest;
use App\Http\Resources\Api\V1\Administration\DepartmentResource;
use Illuminate\Http\JsonResponse;

/** Runs permission-protected department lifecycle commands. */
final class DepartmentController extends Controller
{
    public function store(
        CreateDepartmentRequest $request,
        CreateDepartment $create
    ): JsonResponse {
        try {
            $department = $create->handle(
                new CreateDepartmentCommand(
                    $request->string('code')->toString(),
                    $request->string('name')->toString(),
                )
            );
        } catch (DepartmentCodeAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'department' => new DepartmentResource($department)
            ],
        ], 201);
    }

    public function rename(
        RenameDepartmentRequest $request,
        string $department,
        RenameDepartment $rename,
    ): JsonResponse {
        try {
            $rename->handle(
                new RenameDepartmentCommand(
                    new DepartmentId($department),
                    $request->string('name')->toString(),
                )
            );
        } catch (DepartmentNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveDepartmentCannotChange $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Department renamed successfully.']);
    }

    public function deactivate(string $department, DeactivateDepartment $deactivate): JsonResponse
    {
        try {
            $deactivate->handle(
                new DeactivateDepartmentCommand(
                    new DepartmentId($department)
                )
            );
        } catch (DepartmentNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InactiveDepartmentCannotChange $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Department deactivated successfully.']);
    }
}
