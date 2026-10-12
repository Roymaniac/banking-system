<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\BeginMultiFactorSetupRequest;
use App\Http\Requests\Api\V1\Authentication\CompleteMultiFactorLoginRequest;
use App\Http\Requests\Api\V1\Authentication\DisableMultiFactorRequest;
use App\Http\Requests\Api\V1\Authentication\MultiFactorCodeRequest;
use App\Http\Requests\Api\V1\Authentication\RegenerateRecoveryCodesRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Http\Support\Api\V1\CurrentIdentity;
use App\Http\Support\Api\V1\IssueCustomerAccessToken;
use App\Http\Support\Api\V1\IssueHighValueTransferToken;
use App\Http\Support\Api\V1\IssuePrivilegedAccessToken;
use App\Models\User;
use Identity\Application\MultiFactor\MultiFactorAuthentication;
use Identity\Application\MultiFactor\MultiFactorChallengePurpose;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\Authentication\Exception\InvalidMultiFactorCode;
use Identity\Domain\Authentication\Exception\MultiFactorAlreadyEnabled;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Handles authenticator enrollment and the second login step. */
final class MultiFactorAuthenticationController extends Controller
{
    public function show(
        Request $request,
        CurrentIdentity $identity,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        return response()->json([
            'data' => ['enabled' => $multiFactor->isEnabled($identity->userId($request))],
        ]);
    }

    public function setup(
        BeginMultiFactorSetupRequest $request,
        CurrentIdentity $identity,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        try {
            $setup = $multiFactor->beginSetup(
                $identity->userId($request),
                $request->string('current_password')->toString(),
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (MultiFactorAlreadyEnabled $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => [
            'secret' => $setup->secret,
            'provisioning_uri' => $setup->provisioningUri,
        ]]);
    }

    public function confirm(
        MultiFactorCodeRequest $request,
        CurrentIdentity $identity,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        try {
            $recoveryCodes = $multiFactor->confirmSetup(
                $identity->userId($request),
                $request->string('code')->toString(),
                $identity->sessionId($request),
            );
        } catch (InvalidMultiFactorCode $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Recovery codes are deliberately returned once and never stored raw.
        return response()->json(['data' => [
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ]]);
    }

    public function disable(
        DisableMultiFactorRequest $request,
        CurrentIdentity $identity,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        try {
            $multiFactor->disable(
                $identity->userId($request),
                $request->string('current_password')->toString(),
                $request->string('code')->toString(),
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (InvalidMultiFactorCode $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Multi-factor authentication disabled.']);
    }

    public function regenerateRecoveryCodes(
        RegenerateRecoveryCodesRequest $request,
        CurrentIdentity $identity,
        MultiFactorAuthentication $multiFactor,
    ): JsonResponse {
        try {
            $recoveryCodes = $multiFactor->regenerateRecoveryCodes(
                $identity->userId($request),
                $request->string('current_password')->toString(),
                $request->string('code')->toString(),
            );
        } catch (InvalidCredentials $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (InvalidMultiFactorCode $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // The previous set is now invalid, and this replacement is shown once.
        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function completeLogin(
        CompleteMultiFactorLoginRequest $request,
        MultiFactorAuthentication $multiFactor,
        IssueCustomerAccessToken $tokens,
        IssueHighValueTransferToken $transferTokens,
        IssuePrivilegedAccessToken $privilegedTokens,
        SecurityMonitor $security,
    ): JsonResponse {
        try {
            $completed = $multiFactor->completeLogin(
                $request->string('challenge_token')->toString(),
                $request->string('code')->toString(),
            );
        } catch (InvalidMultiFactorCode $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $user = User::query()->where('identity_user_id', $completed->userId->value())->firstOrFail();

        if ($completed->purpose === MultiFactorChallengePurpose::HighValueTransfer) {
            if ($completed->contextHash === null) {
                return response()->json(['message' => 'The transfer approval is invalid.'], 422);
            }

            $token = $transferTokens->issue($user, $completed->contextHash);

            return response()->json(['data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'authorization' => 'high_value_transfer',
            ]]);
        }

        if ($completed->purpose === MultiFactorChallengePurpose::PrivilegedAccess) {
            try {
                $token = $privilegedTokens->issueVerified(
                    $user,
                    $completed->userId,
                    $completed->deviceName,
                );
            } catch (AccessDenied $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }

            return response()->json(['data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ]]);
        }

        $token = $tokens->issue($user, $completed->deviceName);
        $security->loginSucceeded($completed->userId);

        return response()->json(['data' => [
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ]]);
    }
}
