<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\MakeTransferRequest;
use App\Http\Resources\Api\V1\Transaction\TransferResource;
use App\Http\Support\Api\V1\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Shared\Contracts\Clock;
use Transaction\Application\Transfer\MakeTransfer;
use Transaction\Application\Transfer\MakeTransferCommand;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitExceeded;
use Transaction\Domain\Transfer\Exception\AccountNotEligibleForTransfer;
use Transaction\Domain\Transfer\Exception\DuplicateTransferReference;
use Transaction\Domain\Transfer\Exception\InsufficientTransferFunds;
use Transaction\Domain\Transfer\Exception\SameAccountTransfer;
use Transaction\Domain\Transfer\Exception\TransferCurrencyMismatch;
use Transaction\Domain\Transfer\Exception\TransferLedgerUnavailable;

/** Completes transfers from an owned account to a public account number. */
final class TransferController extends Controller
{
    public function store(
        MakeTransferRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        AccountRepository $accounts,
        MakeTransfer $makeTransfer,
        Clock $clock,
    ): JsonResponse {

        $sender = $currentAccount->find($request, new AccountId($account));

        if ($sender === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $recipient = $accounts->findByNumber(
            new AccountNumber($request->string('recipient_account_number')->toString()),
        );

        if ($recipient === null) {
            return response()->json(['message' => 'The recipient account is unavailable.'], 422);
        }

        try {
            $transfer = $makeTransfer->handle(
                new MakeTransferCommand(
                    senderAccountId: $sender->id(),
                    recipientAccountId: $recipient->id(),
                    minorUnits: $request->integer('minor_units'),
                    reference: $request->string('reference')->toString(),
                    occurredAt: $clock->now(),
                )
            );
        } catch (DuplicateTransferReference $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AccountNotEligibleForTransfer | TransferLedgerUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (
            DailyTransactionLimitExceeded |
            InsufficientTransferFunds |
            SameAccountTransfer |
            TransferCurrencyMismatch $exception
        ) {
            return response()->json([
                'message' => $exception->getMessage()
            ], 422);
        }

        return response()->json([
            'data' => [
                'transfer' => new TransferResource($transfer)
            ],
        ], 201);
    }
}
