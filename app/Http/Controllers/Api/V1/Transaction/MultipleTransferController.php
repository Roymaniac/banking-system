<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transaction;

use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\MakeMultipleTransferRequest;
use App\Http\Resources\Api\V1\Transaction\MultipleTransferResource;
use App\Http\Support\Api\V1\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Shared\Contracts\Clock;
use Transaction\Application\MultipleTransfer\MakeMultipleTransfer;
use Transaction\Application\MultipleTransfer\MakeMultipleTransferCommand;
use Transaction\Application\MultipleTransfer\TransferRecipient;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitExceeded;
use Transaction\Domain\MultipleTransfer\Exception\AccountNotEligibleForMultipleTransfer;
use Transaction\Domain\MultipleTransfer\Exception\DuplicateMultipleTransferReference;
use Transaction\Domain\MultipleTransfer\Exception\InsufficientMultipleTransferFunds;
use Transaction\Domain\MultipleTransfer\Exception\InvalidMultipleTransfer;
use Transaction\Domain\MultipleTransfer\Exception\MultipleTransferCurrencyMismatch;
use Transaction\Domain\MultipleTransfer\Exception\MultipleTransferLedgerUnavailable;

/** Completes one atomic transfer to several public account numbers. */
final class MultipleTransferController extends Controller
{
    public function store(
        MakeMultipleTransferRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        AccountRepository $accounts,
        MakeMultipleTransfer $makeTransfer,
        Clock $clock,
    ): JsonResponse {
        $sender = $currentAccount->find($request, new AccountId($account));

        if ($sender === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $recipients = [];

        foreach ($request->array('recipients') as $recipientInput) {
            $recipient = $accounts->findByNumber(
                new AccountNumber((string) $recipientInput['account_number']),
            );

            if ($recipient === null) {
                return response()->json([
                    'message' => 'One or more recipient accounts are unavailable.',
                ], 422);
            }

            $recipients[] = new TransferRecipient(
                $recipient->id(),
                (int) $recipientInput['minor_units'],
            );
        }

        try {
            $transfer = $makeTransfer->handle(new MakeMultipleTransferCommand(
                senderAccountId: $sender->id(),
                recipients: $recipients,
                reference: $request->string('reference')->toString(),
                occurredAt: $clock->now(),
            ));
        } catch (DuplicateMultipleTransferReference $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AccountNotEligibleForMultipleTransfer | MultipleTransferLedgerUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (
            DailyTransactionLimitExceeded |
            InsufficientMultipleTransferFunds |
            InvalidMultipleTransfer |
            MultipleTransferCurrencyMismatch $exception
        ) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'multiple_transfer' => new MultipleTransferResource($transfer)
            ],
        ], 201);
    }
}
