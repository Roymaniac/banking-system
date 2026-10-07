<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\ReduceDailyTransactionLimitRequest;
use App\Http\Resources\Api\V1\Transaction\DailyTransactionLimitResource;
use App\Http\Resources\Api\V1\Transaction\DailyTransactionLimitView;
use App\Http\Support\Api\V1\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Transaction\Application\DailyLimit\ReduceDailyTransactionLimit;
use Transaction\Application\DailyLimit\ReduceDailyTransactionLimitCommand;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitNotConfigured;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;

/** Lets a customer view and voluntarily lower an owned account's daily limit. */
final class DailyTransactionLimitController extends Controller
{
    public function show(
        Request $request,
        string $account,
        CurrentAccount $currentAccount,
        DailyTransactionLimitRepository $limits,
        Clock $clock,
    ): JsonResponse {

        $accountId = new AccountId($account);

        if ($currentAccount->find($request, $accountId) === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $limit = $limits->find($accountId);

        if ($limit === null) {
            return response()->json(['data' => ['daily_limit' => null]]);
        }

        return response()->json([
            'data' => [
                'daily_limit' => new DailyTransactionLimitResource(
                    new DailyTransactionLimitView(
                        $limit,
                        $limits->usedOn($accountId, $clock->now()),
                    )
                ),
            ],
        ]);
    }

    public function update(
        ReduceDailyTransactionLimitRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        ReduceDailyTransactionLimit $reduceLimit,
        DailyTransactionLimitRepository $limits,
        Clock $clock,
    ): JsonResponse {

        $accountId = new AccountId($account);

        if ($currentAccount->find($request, $accountId) === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        try {
            $limit = $reduceLimit->handle(
                new ReduceDailyTransactionLimitCommand(
                    $accountId,
                    $request->integer('maximum_minor_units'),
                )
            );
        } catch (DailyTransactionLimitNotConfigured $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'daily_limit' => new DailyTransactionLimitResource(
                    new DailyTransactionLimitView(
                        $limit,
                        $limits->usedOn($accountId, $clock->now()),
                    )
                ),
            ],
        ]);
    }
}
