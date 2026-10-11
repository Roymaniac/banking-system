<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use Account\Application\Closure\CloseAccount;
use Account\Application\Closure\CloseAccountCommand;
use Account\Application\Freeze\FreezeAccount;
use Account\Application\Freeze\FreezeAccountCommand;
use Account\Application\Freeze\UnfreezeAccount;
use Account\Application\Freeze\UnfreezeAccountCommand;
use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\Exception\NonZeroAccountBalance;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\ClosureReason;
use Account\Domain\Account\ValueObject\FreezeReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\CloseAccountRequest;
use App\Http\Requests\Api\V1\Operation\FreezeAccountRequest;
use App\Http\Support\Api\V1\RequestCorrelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides audited staff controls for restricting and closing accounts. */
final class AccountLifecycleController extends Controller
{
    public function freeze(
        FreezeAccountRequest $request,
        string $account,
        FreezeAccount $freeze,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $freeze->handle(new FreezeAccountCommand(
                new AccountId($account),
                FreezeReason::from($request->string('reason')->toString()),
                $correlation->from($request),
            ));
        } catch (AccountNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidAccountStatusTransition $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Account frozen successfully.']);
    }

    public function unfreeze(
        Request $request,
        string $account,
        UnfreezeAccount $unfreeze,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $unfreeze->handle(new UnfreezeAccountCommand(
                new AccountId($account),
                $correlation->from($request),
            ));
        } catch (AccountNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidAccountStatusTransition $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Account unfrozen successfully.']);
    }

    public function close(
        CloseAccountRequest $request,
        string $account,
        CloseAccount $close,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $close->handle(new CloseAccountCommand(
                new AccountId($account),
                ClosureReason::from($request->string('reason')->toString()),
                $correlation->from($request),
            ));
        } catch (AccountNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (NonZeroAccountBalance $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (InvalidAccountStatusTransition $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Account closed successfully.']);
    }
}
