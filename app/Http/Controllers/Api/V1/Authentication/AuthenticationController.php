<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\LoginRequest;
use App\Http\Requests\Api\V1\Authentication\PrivilegedAccessRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Http\Support\Api\V1\IssueCustomerAccessToken;
use App\Http\Support\Api\V1\IssuePrivilegedAccessToken;
use App\Models\User as LaravelUser;
use Identity\Application\Authentication\AuthenticateUser;
use Identity\Application\Authentication\AuthenticateUserCommand;
use Identity\Application\MultiFactor\MultiFactorAuthentication;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\Authentication\Exception\EmailNotVerified;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Translates API authentication requests into Identity use-case calls. */
final class AuthenticationController extends Controller
{
    public function login(
        LoginRequest $request,
        AuthenticateUser $authenticate,
        MultiFactorAuthentication $multiFactor,
        IssueCustomerAccessToken $tokens,
        SecurityMonitor $security,
    ): JsonResponse {
        try {
            $identity = $authenticate->handle(
                new AuthenticateUserCommand(
                    $request->string('email')->toString(),
                    $request->string('password')->toString(),
                )
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (EmailNotVerified $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $user = LaravelUser::query()
            ->where('identity_user_id', $identity->id()->value())
            ->firstOrFail();

        if ($multiFactor->isEnabled($identity->id())) {
            $challenge = $multiFactor->beginLogin(
                $identity->id(),
                $request->string('device_name')->toString(),
            );

            return response()->json(['data' => [
                'mfa_required' => true,
                'challenge_token' => $challenge['token'],
                'expires_at' => $challenge['expires_at']->format(DATE_ATOM),
            ]], 202);
        }

        $token = $tokens->issue($user, $request->string('device_name')->toString());
        $security->loginSucceeded($identity->id());

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function privilegedAccess(
        PrivilegedAccessRequest $request,
        IssuePrivilegedAccessToken $issuer,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        /** @var LaravelUser $user */
        $user = $request->user();

        try {
            $userId = $issuer->verify(
                $user,
                $request->string('password')->toString(),
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (AccessDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        if ($multiFactor->isEnabled($userId)) {
            $challenge = $multiFactor->beginPrivilegedAccess(
                $userId,
                $request->string('device_name')->toString(),
            );

            return response()->json(['data' => [
                'mfa_required' => true,
                'challenge_token' => $challenge['token'],
                'expires_at' => $challenge['expires_at']->format(DATE_ATOM),
            ]], 202);
        }

        $token = $issuer->issueVerified(
            $user,
            $userId,
            $request->string('device_name')->toString(),
        );

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        /** @var LaravelUser $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
