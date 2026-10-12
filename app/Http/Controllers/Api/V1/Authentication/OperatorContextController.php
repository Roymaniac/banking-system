<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use Administration\Application\Security\OperatorContextQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\CurrentIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gives the operations portal the current staff member's safe access context. */
final class OperatorContextController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentIdentity $identity,
        OperatorContextQuery $operators,
    ): JsonResponse {
        $operator = $operators->forUser($identity->userId($request));

        if ($operator === null) {
            return response()->json([
                'message' => 'Active staff access is required.',
            ], 403);
        }

        return response()->json([
            'data' => ['operator' => $operator],
        ]);
    }
}
