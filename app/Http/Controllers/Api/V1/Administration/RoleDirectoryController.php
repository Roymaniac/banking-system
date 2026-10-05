<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Directory\AdministrationDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ListAdministrationRecordsRequest;
use Illuminate\Http\JsonResponse;

/** Provides role summaries and detailed permission assignments. */
final class RoleDirectoryController extends Controller
{
    public function index(
        ListAdministrationRecordsRequest $request,
        AdministrationDirectoryQuery $directory,
    ): JsonResponse {

        $result = $directory->roles(
            $request->pageNumber(),
            $request->pageSize(),
            $request->searchTerm(),
            $request->requestedStatus(),
        );

        return response()->json([
            'data' => ['roles' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }

    public function show(
        string $role,
        AdministrationDirectoryQuery $directory
    ): JsonResponse {

        $record = $directory->role($role);

        return $record === null
            ? response()->json(['message' => 'Role not found.'], 404)
            : response()->json(['data' => ['role' => $record]]);
    }
}
