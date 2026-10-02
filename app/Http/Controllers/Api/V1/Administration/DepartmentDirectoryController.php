<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Directory\AdministrationDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ListAdministrationRecordsRequest;
use Illuminate\Http\JsonResponse;

/** Provides read-only department views for administration screens. */
final class DepartmentDirectoryController extends Controller
{
    public function index(
        ListAdministrationRecordsRequest $request,
        AdministrationDirectoryQuery $directory,
    ): JsonResponse {

        $result = $directory->departments(
            $request->pageNumber(),
            $request->pageSize(),
            $request->searchTerm(),
            $request->requestedStatus(),
        );

        return response()->json([
            'data' => ['departments' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }

    public function show(
        string $department,
        AdministrationDirectoryQuery $directory
    ): JsonResponse {

        $record = $directory->department($department);

        return $record === null
            ? response()->json(['message' => 'Department not found.'], 404)
            : response()->json(['data' => ['department' => $record]]);
    }
}
