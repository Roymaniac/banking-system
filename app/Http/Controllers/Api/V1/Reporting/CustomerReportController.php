<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reporting\CustomerReportResource;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Customer\Exception\CustomerReportNotFound;
use Reporting\Application\Customer\GetCustomerReport;

/** Returns a complete customer overview to authorized reporting staff. */
final class CustomerReportController extends Controller
{
    public function show(string $customer, GetCustomerReport $getReport): JsonResponse
    {
        try {
            $report = $getReport->handle(new CustomerId($customer));
        } catch (CustomerReportNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json([
            'data' => ['report' => new CustomerReportResource($report)],
        ]);
    }
}
