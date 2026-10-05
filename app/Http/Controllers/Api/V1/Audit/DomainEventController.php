<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Audit\ListAuditRecordsRequest;
use Audit\Application\Query\AuditTrailQuery;
use Illuminate\Http\JsonResponse;

/** Lists permanent domain-change records for authorized investigators. */
final class DomainEventController extends Controller
{
    public function index(
        ListAuditRecordsRequest $request,
        AuditTrailQuery $auditTrail,
    ): JsonResponse {

        $result = $auditTrail->domainEvents(
            $request->filters(),
            $request->pageNumber(),
            $request->pageSize(),
        );

        return response()->json([
            'data' => ['events' => $result['items']],
            'meta' => $result['pagination'],
        ]);
    }
}
