<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\LoginRequest;
use App\Http\Requests\Api\V1\Authentication\PrivilegedAccessRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Http\Support\Api\V1\IssuePrivilegedAccessToken;
use App\Models\User as LaravelUser;
use DateInterval;
use Identity\Application\Authentication\AuthenticateUser;
use Identity\Application\Authentication\AuthenticateUserCommand;
use Identity\Domain\Authentication\Exception\EmailNotVerified;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Contracts\Clock;

/** Translates API authentication requests into Identity use-case calls. */
final class AuthenticationController extends Controller
{
    public function login(
        LoginRequest $request,
        AuthenticateUser $authenticate,
        Clock $clock,
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

        $expiresAt = $clock->now()->add(new DateInterval(sprintf(
            'PT%dM',
            max(1, (int) config('sanctum.expiration', 1440)),
        )));
        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['customer'],
            $expiresAt,
        );

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $expiresAt->format(DATE_ATOM),
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function privilegedAccess(
        PrivilegedAccessRequest $request,
        IssuePrivilegedAccessToken $issuer,
    ): JsonResponse {
        /** @var LaravelUser $user */
        $user = $request->user();

        try {
            $token = $issuer->issue(
                $user,
                $request->string('password')->toString(),
                $request->string('device_name')->toString(),
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (AccessDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

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
