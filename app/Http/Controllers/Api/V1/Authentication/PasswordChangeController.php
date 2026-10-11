<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\ChangePasswordRequest;
use App\Http\Support\Api\V1\RequestCorrelation;
use App\Models\User as LaravelUser;
use Identity\Application\PasswordChange\ChangePassword;
use Identity\Application\PasswordChange\ChangePasswordCommand;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\JsonResponse;

/** Handles password changes for an already authenticated customer. */
final class PasswordChangeController extends Controller
{
    public function __invoke(
        ChangePasswordRequest $request,
        ChangePassword $changePassword,
        RequestCorrelation $correlation,
    ): JsonResponse {
        /** @var LaravelUser $authenticationUser */
        $authenticationUser = $request->user();
        $sessionId = (int) $authenticationUser->currentAccessToken()->getKey();

        try {
            $changePassword->handle(new ChangePasswordCommand(
                new UserId((string) $authenticationUser->identity_user_id),
                $request->string('current_password')->toString(),
                $request->string('password')->toString(),
                $sessionId,
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
