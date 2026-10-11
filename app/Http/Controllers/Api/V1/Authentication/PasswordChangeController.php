<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\ChangePasswordRequest;
use App\Http\Support\Api\V1\CurrentIdentity;
use App\Http\Support\Api\V1\RequestCorrelation;
use Identity\Application\PasswordChange\ChangePassword;
use Identity\Application\PasswordChange\ChangePasswordCommand;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Illuminate\Http\JsonResponse;

/** Handles password changes for an already authenticated customer. */
final class PasswordChangeController extends Controller
{
    public function __invoke(
        ChangePasswordRequest $request,
        ChangePassword $changePassword,
        CurrentIdentity $identity,
        RequestCorrelation $correlation,
    ): JsonResponse {
        try {
            $changePassword->handle(new ChangePasswordCommand(
                $identity->userId($request),
                $request->string('current_password')->toString(),
                $request->string('password')->toString(),
                $identity->sessionId($request),
                $correlation->from($request),
            ));
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return response()->json([
            'message' => 'Password changed successfully. Other devices have been signed out.',
        ]);
    }
}
