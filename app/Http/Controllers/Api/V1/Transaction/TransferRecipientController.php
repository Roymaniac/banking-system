<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\ConfirmTransferRecipientRequest;
use App\Http\Support\Api\V1\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Transaction\Application\Transfer\RecipientAccountLookup;

/** Confirms a compatible recipient before the customer reviews a transfer. */
final class TransferRecipientController extends Controller
{
    public function __invoke(
        ConfirmTransferRecipientRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        RecipientAccountLookup $recipients,
    ): JsonResponse {
        $sender = $currentAccount->find($request, new AccountId($account));

        if ($sender === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        if (! $sender->isActive()) {
            return response()->json([
                'message' => 'The sender account is not available for transfers.',
            ], 409);
        }

        $recipient = $recipients->findCompatible(
            new AccountNumber($request->string('recipient_account_number')->toString()),
            $sender->currency(),
        );

        if ($recipient === null || $recipient->accountId->equals($sender->id())) {
            return response()->json([
                'message' => 'The recipient account is unavailable.',
            ], 422);
        }

        return response()->json(['data' => ['recipient' => [
            'display_name' => $recipient->displayName,
            'account_last_four' => $recipient->accountLastFour,
            'currency' => $recipient->currency,
        ]]]);
    }
}
