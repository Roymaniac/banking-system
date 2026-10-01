<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\TransactionHistoryRequest;
use App\Http\Resources\Api\V1\Transaction\TransactionHistoryResource;
use App\Http\Support\Api\V1\CurrentAccount;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Reporting\Application\Transaction\Exception\TransactionReportNotFound;
use Reporting\Application\Transaction\GetTransactionReport;
use Reporting\Application\Transaction\TransactionReportPeriod;

/** Returns bounded posted transaction history for an owned account. */
final class TransactionHistoryController extends Controller
{
    public function index(
        TransactionHistoryRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        GetTransactionReport $getReport,
    ): JsonResponse {

        $accountId = new AccountId($account);

        if ($currentAccount->find($request, $accountId) === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $timezone = new DateTimeZone((string) config('app.timezone', 'UTC'));
        $from = new DateTimeImmutable($request->string('from')->toString(), $timezone);
        $to = new DateTimeImmutable($request->string('to')->toString(), $timezone);
        $period = new TransactionReportPeriod(
            from: $from->setTime(0, 0),
            to: $to->setTime(23, 59, 59, 999999),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 50),
        );

        try {
            $report = $getReport->handle($accountId, $period);
        } catch (TransactionReportNotFound) {
            return response()->json([
                'message' => 'Transaction history is unavailable until the account is active.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'history' => new TransactionHistoryResource($report)
            ],
        ]);
    }
}
