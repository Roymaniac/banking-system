<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Audit\ListAuditRecordsRequest;
use Audit\Application\Query\AuditTrailQuery;
use Illuminate\Http\JsonResponse;

/** Lists authenticated actions without exposing any audit mutation operation. */
final class ActivityController extends Controller
{
    public function index(
        ListAuditRecordsRequest $request,
        AuditTrailQuery $auditTrail,
    ): JsonResponse {

        $result = $auditTrail->activities(
            $request->filters(),
            $request->pageNumber(),
            $request->pageSize(),
        );

        return response()->json([
            'data' => ['activities' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }
}
