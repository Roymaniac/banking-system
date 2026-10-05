<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use Administration\Application\Directory\AdministrationDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ListAdministrationRecordsRequest;
use Illuminate\Http\JsonResponse;

/** Provides staff directory views while keeping write controllers focused on commands. */
final class StaffDirectoryController extends Controller
{
    public function index(
        ListAdministrationRecordsRequest $request,
        AdministrationDirectoryQuery $directory,
    ): JsonResponse {

        $result = $directory->staff(
            $request->pageNumber(),
            $request->pageSize(),
            $request->searchTerm(),
            $request->requestedStatus(),
        );

        return response()->json([
            'data' => ['staff' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }

    public function show(
        string $staff,
        AdministrationDirectoryQuery $directory
    ): JsonResponse {

        $record = $directory->staffMember($staff);

        return $record === null
            ? response()->json(['message' => 'Staff member not found.'], 404)
            : response()->json(['data' => ['staff' => $record]]);
    }
}
