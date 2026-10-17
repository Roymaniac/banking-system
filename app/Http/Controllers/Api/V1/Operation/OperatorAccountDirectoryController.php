<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ListOperatorAccountsRequest;
use App\Http\Support\Api\V1\OperatorLookupAccess;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Operator\OperatorResourceQuery;

/** Resolves accounts for authorized operator actions without exposing full numbers. */
final class OperatorAccountDirectoryController extends Controller
{
    public function index(
        ListOperatorAccountsRequest $request,
        OperatorLookupAccess $access,
        OperatorResourceQuery $resources,
    ): JsonResponse {
        try {
            $access->account($request, $request->purpose());
        } catch (AccessDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $result = $resources->accounts($request->filters());

        return $this->noStore(response()->json([
            'data' => ['items' => $result['items']],
            'meta' => $result['pagination'],
        ]));
    }

    public function show(
        ListOperatorAccountsRequest $request,
        string $account,
        OperatorLookupAccess $access,
        OperatorResourceQuery $resources,
    ): JsonResponse {
        try {
            $access->account($request, $request->purpose());
        } catch (AccessDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $record = $resources->account($account, $request->purpose());

        return $record === null
            ? $this->noStore(response()->json(['message' => 'Account not found.'], 404))
            : $this->noStore(response()->json(['data' => ['account' => $record]]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store');
    }
}
