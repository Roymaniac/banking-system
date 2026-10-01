<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ledger;

use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ledger\BalanceResource;
use App\Http\Support\Api\V1\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ledger\Application\Balance\GetLedgerBalance;
use Ledger\Domain\Ledger\Repository\LedgerRepository;

/** Returns the authoritative posted balance for an owned account. */
final class AccountBalanceController extends Controller
{
    public function show(
        Request $request,
        string $account,
        CurrentAccount $currentAccount,
        LedgerRepository $ledgers,
        GetLedgerBalance $getBalance,
    ): JsonResponse {
        $ownedAccount = $currentAccount->find($request, new AccountId($account));

        if ($ownedAccount === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $ledger = $ledgers->findByAccountId($ownedAccount->id());

        if ($ledger === null) {
            return response()->json([
                'message' => 'The account balance is unavailable until the account is active.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'account_id' => $ownedAccount->id()->value(),
                'balance' => new BalanceResource($getBalance->handle($ledger->id())),
            ],
        ]);
    }
}
