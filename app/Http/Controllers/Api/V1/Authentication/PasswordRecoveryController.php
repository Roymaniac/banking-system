<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Authentication\ResetPasswordRequest;
use App\Http\Support\Api\V1\RequestCorrelation;
use Identity\Application\PasswordReset\RequestPasswordReset;
use Identity\Application\PasswordReset\RequestPasswordResetCommand;
use Identity\Application\PasswordReset\ResetPassword;
use Identity\Application\PasswordReset\ResetPasswordCommand;
use Identity\Domain\PasswordReset\Exception\InvalidPasswordResetToken;
use Illuminate\Http\JsonResponse;

/** Handles public password recovery without revealing registered emails. */
final class PasswordRecoveryController extends Controller
{
    public function request(
        ForgotPasswordRequest $request,
        RequestPasswordReset $passwordReset,
    ): JsonResponse {
        $passwordReset->handle(new RequestPasswordResetCommand(
            $request->string('email')->toString(),
        ));

        // Unknown and registered emails receive the same response so this
        // endpoint cannot be used to discover who banks here.
        return response()->json([
            'message' => 'If the email belongs to an account, password-reset instructions have been sent.',
        ], 202);
    }

    public function reset(
        ResetPasswordRequest $request,
        ResetPassword $passwordReset,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $passwordReset->handle(new ResetPasswordCommand(
                $request->string('token')->toString(),
                $request->string('password')->toString(),
                $correlation->from($request),
            ));
        } catch (InvalidPasswordResetToken $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully. Sign in again.',
        ]);
    }
}
