<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\ReportPeriodRequest;
use App\Http\Resources\Api\V1\Reporting\TransactionReportResource;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Transaction\Exception\TransactionReportNotFound;
use Reporting\Application\Transaction\GetTransactionReport;
use Reporting\Application\Transaction\TransactionReportPeriod;

/** Returns an account statement to staff with transaction-report access. */
final class TransactionReportController extends Controller
{
    public function show(
        ReportPeriodRequest $request,
        string $account,
        GetTransactionReport $getReport,
    ): JsonResponse {
        try {

            $report = $getReport->handle(
                new AccountId($account),
                new TransactionReportPeriod(
                    $request->from(),
                    $request->to(),
                    $request->pageNumber(),
                    $request->pageSize(),
                ),
            );
        } catch (TransactionReportNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json([
            'data' => ['report' => new TransactionReportResource($report)],
        ]);
    }
}
