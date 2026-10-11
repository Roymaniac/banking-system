<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\RegisterRequest;
use App\Http\Requests\Api\V1\Authentication\ResendEmailVerificationRequest;
use App\Http\Requests\Api\V1\Authentication\VerifyEmailRequest;
use App\Http\Support\Api\V1\RequestCorrelation;
use Identity\Application\EmailVerification\RequestEmailVerificationByEmail;
use Identity\Application\EmailVerification\VerifyEmail;
use Identity\Application\EmailVerification\VerifyEmailCommand;
use Identity\Application\Registration\RegisterUser;
use Identity\Application\Registration\RegisterUserCommand;
use Identity\Domain\EmailVerification\Exception\InvalidEmailVerificationToken;
use Identity\Domain\User\Exception\EmailAlreadyRegistered;
use Illuminate\Http\JsonResponse;

/** Handles public signup and proof of email ownership. */
final class RegistrationController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUser $register,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $register->handle(new RegisterUserCommand(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $correlation->from($request),
            ));
        } catch (EmailAlreadyRegistered $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Registration successful. Check your email to verify the account.',
        ], 201);
    }

    public function verify(
        VerifyEmailRequest $request,
        VerifyEmail $verify,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $verify->handle(new VerifyEmailCommand(
                $request->string('token')->toString(),
                $correlation->from($request),
            ));
        } catch (InvalidEmailVerificationToken $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resend(
        ResendEmailVerificationRequest $request,
        RequestEmailVerificationByEmail $resend,
    ): JsonResponse {
        $resend->handle($request->string('email')->toString());

        // The response is identical for unknown, verified, and unverified users.
        return response()->json([
            'message' => 'If the account needs verification, a new email has been sent.',
        ], 202);
    }
}
