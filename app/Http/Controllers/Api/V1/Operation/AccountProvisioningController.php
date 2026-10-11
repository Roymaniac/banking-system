<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use Account\Application\Provisioning\ProvisionAccount;
use Account\Application\Provisioning\ProvisionAccountCommand;
use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Exception\AccountNumberAlreadyAssigned;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Operation\AccountProvisioningResource;
use App\Http\Support\Api\V1\RequestCorrelation;
use Compliance\Domain\DueDiligence\Exception\InvalidDueDiligenceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ledger\Domain\Ledger\Exception\LedgerAlreadyExists;

/** Lets trusted staff complete an approved customer's account onboarding. */
final class AccountProvisioningController extends Controller
{
    public function store(
        Request $request,
        string $account,
        ProvisionAccount $provision,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $result = $provision->handle(new ProvisionAccountCommand(
                new AccountId($account),
                $correlation->from($request),
            ));
        } catch (AccountNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidDueDiligenceAction $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (AccountNumberAlreadyAssigned|InvalidAccountStatusTransition|LedgerAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => ['provisioning' => new AccountProvisioningResource($result)],
        ]);
    }
}
