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
use App\Http\Support\Api\V1\CurrentIdentity;
use App\Http\Support\Api\V1\HighValueTransferApproval;
use App\Models\User;
use Dedoc\Scramble\Attributes\Parameter;
use Identity\Application\MultiFactor\MultiFactorAuthentication;
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
use Transaction\Domain\Risk\Exception\SuspiciousTransferVelocity;

/** Completes one atomic transfer to several public account numbers. */
final class MultipleTransferController extends Controller
{
    #[Parameter(in: 'header', name: 'Idempotency-Key', description: 'Unique retry key for this intended operation.', required: true, type: 'string', infer: false, example: '0199f4f2-9b31-70f2-8a29-8a706bfcf501')]
    public function store(
        MakeMultipleTransferRequest $request,
        string $account,
        CurrentAccount $currentAccount,
        CurrentIdentity $identity,
        AccountRepository $accounts,
        MakeMultipleTransfer $makeTransfer,
        MultiFactorAuthentication $multiFactor,
        HighValueTransferApproval $approval,
        Clock $clock,
    ): JsonResponse {
        $sender = $currentAccount->find($request, new AccountId($account));

        if ($sender === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $recipients = [];
        $approvalRecipients = [];
        $totalMinorUnits = 0;

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
            $approvalRecipients[] = [
                'account_id' => $recipient->id()->value(),
                'minor_units' => (int) $recipientInput['minor_units'],
            ];
            $totalMinorUnits += (int) $recipientInput['minor_units'];
        }

        if ($approval->isRequired($totalMinorUnits)) {
            $userId = $identity->userId($request);
            $fingerprint = $approval->batchFingerprint(
                $userId,
                $sender->id(),
                $approvalRecipients,
                $request->string('reference')->toString(),
                (string) $request->header('Idempotency-Key'),
            );
            /** @var User $authenticatedUser */
            $authenticatedUser = $request->user();

            if (! $authenticatedUser->tokenCan($approval->ability($fingerprint))) {
                if (! $multiFactor->isEnabled($userId)) {
                    return response()->json([
                        'message' => 'Enable multi-factor authentication before making a high-value transfer.',
                    ], 403);
                }

                $challenge = $multiFactor->beginHighValueTransfer($userId, $fingerprint);

                return response()->json(['data' => [
                    'mfa_required' => true,
                    'purpose' => 'high_value_transfer',
                    'challenge_token' => $challenge['token'],
                    'expires_at' => $challenge['expires_at']->format(DATE_ATOM),
                ]], 202);
            }
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
        } catch (AccountNotEligibleForMultipleTransfer|MultipleTransferLedgerUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (SuspiciousTransferVelocity $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        } catch (
            DailyTransactionLimitExceeded|
            InsufficientMultipleTransferFunds|
            InvalidMultipleTransfer|
            MultipleTransferCurrencyMismatch $exception
        ) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'multiple_transfer' => new MultipleTransferResource($transfer),
            ],
        ], 201);
    }
}
