<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ListOperatorCustomersRequest;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Operator\OperatorResourceQuery;

/** Resolves minimal customer identities for authorized report selection. */
final class OperatorCustomerDirectoryController extends Controller
{
    public function index(
        ListOperatorCustomersRequest $request,
        OperatorResourceQuery $resources,
    ): JsonResponse {
        $result = $resources->customers($request->filters());

        return $this->noStore(response()->json([
            'data' => ['items' => $result['items']],
            'meta' => $result['pagination'],
        ]));
    }

    public function show(string $customer, OperatorResourceQuery $resources): JsonResponse
    {
        $record = $resources->customer($customer);

        return $record === null
            ? $this->noStore(response()->json(['message' => 'Customer not found.'], 404))
            : $this->noStore(response()->json(['data' => ['customer' => $record]]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store');
    }
}
