<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\ReportPeriodRequest;
use App\Http\Resources\Api\V1\Reporting\LedgerReportResource;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Ledger\GetLedgerReport;
use Reporting\Application\Ledger\LedgerReportRequest;

/** Returns a paginated general-ledger report and its control totals. */
final class LedgerReportController extends Controller
{
    public function show(
        ReportPeriodRequest $request,
        GetLedgerReport $getReport
    ): JsonResponse {

        $report = $getReport->handle(
            new LedgerReportRequest(
                $request->from(),
                $request->to(),
                $request->pageNumber(),
                $request->pageSize(),
            )
        );

        return response()->json([
            'data' => ['report' => new LedgerReportResource($report)],
        ]);
    }
}
