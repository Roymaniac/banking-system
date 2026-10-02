<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Directory\AdministrationDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ListAdministrationRecordsRequest;
use Illuminate\Http\JsonResponse;

/** Provides permission catalogue views and shows which roles use each permission. */
final class PermissionDirectoryController extends Controller
{
    public function index(
        ListAdministrationRecordsRequest $request,
        AdministrationDirectoryQuery $directory,
    ): JsonResponse {

        $result = $directory->permissions(
            $request->pageNumber(),
            $request->pageSize(),
            $request->searchTerm(),
        );

        return response()->json([
            'data' => ['permissions' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }

    public function show(
        string $permission,
        AdministrationDirectoryQuery $directory
    ): JsonResponse {

        $record = $directory->permission($permission);

        return $record === null
            ? response()->json(['message' => 'Permission not found.'], 404)
            : response()->json(['data' => ['permission' => $record]]);
    }
}
