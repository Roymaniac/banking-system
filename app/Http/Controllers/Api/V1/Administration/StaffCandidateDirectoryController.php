<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\ListStaffCandidatesRequest;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Operator\OperatorResourceQuery;

/** Resolves verified sign-in identities that may be onboarded as staff. */
final class StaffCandidateDirectoryController extends Controller
{
    public function index(
        ListStaffCandidatesRequest $request,
        OperatorResourceQuery $resources,
    ): JsonResponse {
        $result = $resources->staffCandidates($request->filters());

        return $this->noStore(response()->json([
            'data' => ['items' => $result['items']],
            'meta' => $result['pagination'],
        ]));
    }

    public function show(string $user, OperatorResourceQuery $resources): JsonResponse
    {
        $record = $resources->staffCandidate($user);

        return $record === null
            ? $this->noStore(response()->json(['message' => 'Staff candidate not found.'], 404))
            : $this->noStore(response()->json(['data' => ['staff_candidate' => $record]]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store');
    }
}
