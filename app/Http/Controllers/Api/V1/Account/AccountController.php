<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use Account\Application\Opening\CreateAccount;
use Account\Application\Opening\CreateAccountCommand;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\CreateAccountRequest;
use App\Http\Resources\Api\V1\Account\AccountResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lets signed-in customers request and view only their own accounts. */
final class AccountController extends Controller
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function store(
        CreateAccountRequest $request,
        CurrentCustomer $currentCustomer,
        CreateAccount $createAccount,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        $account = $createAccount->handle(
            new CreateAccountCommand(
                customerId: $customer->id(),
                type: AccountType::from($request->string('type')->toString()),
                currency: $request->string('currency')->toString(),
            )
        );

        return response()->json([
            'data' => [
                'account' => new AccountResource($account)
            ],
        ], 201);
    }

    public function index(Request $request, CurrentCustomer $currentCustomer): JsonResponse
    {
        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'accounts' => AccountResource::collection(
                    $this->accounts->findByCustomerId($customer->id()),
                ),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $account,
        CurrentCustomer $currentCustomer,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        $storedAccount = $this->accounts->findById(new AccountId($account));

        if ($storedAccount === null || ! $storedAccount->customerId()->equals($customer->id())) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        return response()->json([
            'data' => [
                'account' => new AccountResource($storedAccount)
            ],
        ]);
    }
}
