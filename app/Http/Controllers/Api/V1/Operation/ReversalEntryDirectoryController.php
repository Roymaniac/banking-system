<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ListReversalEntriesRequest;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Operator\OperatorResourceQuery;

/** Resolves exact original ledger-entry IDs for transaction reversal review. */
final class ReversalEntryDirectoryController extends Controller
{
    public function index(
        ListReversalEntriesRequest $request,
        OperatorResourceQuery $resources,
    ): JsonResponse {
        $result = $resources->ledgerEntries($request->filters());

        return $this->noStore(response()->json([
            'data' => ['items' => $result['items']],
            'meta' => $result['pagination'],
        ]));
    }

    public function show(string $entry, OperatorResourceQuery $resources): JsonResponse
    {
        $record = $resources->ledgerEntry($entry);

        return $record === null
            ? $this->noStore(response()->json(['message' => 'Ledger entry not found.'], 404))
            : $this->noStore(response()->json(['data' => ['ledger_entry' => $record]]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store');
    }
}
