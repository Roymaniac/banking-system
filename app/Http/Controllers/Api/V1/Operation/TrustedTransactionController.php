<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\MoneyMovementRequest;
use App\Http\Requests\Api\V1\Operation\ReverseTransactionRequest;
use App\Http\Resources\Api\V1\Operation\MoneyMovementResource;
use App\Http\Resources\Api\V1\Operation\ReversalResource;
use App\Support\Banking\ConfiguredSettlementLedgers;
use Illuminate\Http\JsonResponse;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Contracts\Clock;
use Transaction\Application\Deposit\MakeDeposit;
use Transaction\Application\Deposit\MakeDepositCommand;
use Transaction\Application\Reversal\ReverseTransaction;
use Transaction\Application\Reversal\ReverseTransactionCommand;
use Transaction\Application\Withdrawal\MakeWithdrawal;
use Transaction\Application\Withdrawal\MakeWithdrawalCommand;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitExceeded;
use Transaction\Domain\Deposit\Exception\AccountNotEligibleForDeposit;
use Transaction\Domain\Deposit\Exception\DepositCurrencyMismatch;
use Transaction\Domain\Deposit\Exception\DepositLedgerUnavailable;
use Transaction\Domain\Deposit\Exception\DuplicateDepositReference;
use Transaction\Domain\Reversal\Exception\DuplicateReversalReference;
use Transaction\Domain\Reversal\Exception\InsufficientReversalFunds;
use Transaction\Domain\Reversal\Exception\ReversibleEntryNotFound;
use Transaction\Domain\Reversal\Exception\TransactionAlreadyReversed;
use Transaction\Domain\Withdrawal\Exception\AccountNotEligibleForWithdrawal;
use Transaction\Domain\Withdrawal\Exception\DuplicateWithdrawalReference;
use Transaction\Domain\Withdrawal\Exception\InsufficientFunds;
use Transaction\Domain\Withdrawal\Exception\WithdrawalCurrencyMismatch;
use Transaction\Domain\Withdrawal\Exception\WithdrawalLedgerUnavailable;

/** Handles permission-protected money operations performed by trusted systems. */
final class TrustedTransactionController extends Controller
{
    public function deposit(
        MoneyMovementRequest $request,
        string $account,
        AccountRepository $accounts,
        ConfiguredSettlementLedgers $settlementLedgers,
        MakeDeposit $makeDeposit,
        Clock $clock,
    ): JsonResponse {

        $storedAccount = $accounts->findById(new AccountId($account));

        if ($storedAccount === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $fundingLedger = $settlementLedgers->find('deposit', $storedAccount->currency()->value());

        if ($fundingLedger === null) {
            return $this->settlementUnavailable();
        }

        try {
            $deposit = $makeDeposit->handle(
                new MakeDepositCommand(
                    $storedAccount->id(),
                    $fundingLedger,
                    $request->integer('minor_units'),
                    $request->string('reference')->toString(),
                    $clock->now(),
                )
            );
        } catch (DuplicateDepositReference $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AccountNotEligibleForDeposit|DepositCurrencyMismatch|DepositLedgerUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'deposit' => new MoneyMovementResource($deposit),
            ],
        ], 201);
    }

    public function withdraw(
        MoneyMovementRequest $request,
        string $account,
        AccountRepository $accounts,
        ConfiguredSettlementLedgers $settlementLedgers,
        MakeWithdrawal $makeWithdrawal,
        Clock $clock,
    ): JsonResponse {

        $storedAccount = $accounts->findById(new AccountId($account));

        if ($storedAccount === null) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $disbursementLedger = $settlementLedgers->find('withdrawal', $storedAccount->currency()->value());

        if ($disbursementLedger === null) {
            return $this->settlementUnavailable();
        }

        try {
            $withdrawal = $makeWithdrawal->handle(
                new MakeWithdrawalCommand(
                    $storedAccount->id(),
                    $disbursementLedger,
                    $request->integer('minor_units'),
                    $request->string('reference')->toString(),
                    $clock->now(),
                )
            );
        } catch (DuplicateWithdrawalReference $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (DailyTransactionLimitExceeded|InsufficientFunds $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (AccountNotEligibleForWithdrawal|WithdrawalCurrencyMismatch|WithdrawalLedgerUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'withdrawal' => new MoneyMovementResource($withdrawal),
            ],
        ], 201);
    }

    public function reverse(
        ReverseTransactionRequest $request,
        string $entry,
        ReverseTransaction $reverseTransaction,
        Clock $clock,
    ): JsonResponse {
        try {

            $reversal = $reverseTransaction->handle(new ReverseTransactionCommand(
                new LedgerEntryId($entry),
                $request->string('reference')->toString(),
                $request->string('reason')->toString(),
                $clock->now(),
            ));
        } catch (DuplicateReversalReference|TransactionAlreadyReversed $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (InsufficientReversalFunds|ReversibleEntryNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'reversal' => new ReversalResource($reversal),
            ],
        ], 201);
    }

    private function settlementUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Settlement is not configured for this account currency.',
        ], 503);
    }
}
