<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authentication\ListSessionsRequest;
use App\Http\Resources\Api\V1\Identity\UserSessionResource;
use App\Http\Support\Api\V1\CurrentIdentity;
use Identity\Application\Security\SecurityMonitor;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Application\Session\UserSessionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lets customers inspect and end only their own API sessions. */
final class SessionController extends Controller
{
    public function index(
        ListSessionsRequest $request,
        CurrentIdentity $identity,
        UserSessionQuery $sessions,
    ): JsonResponse {
        $page = $sessions->paginate(
            $identity->userId($request),
            $identity->sessionId($request),
            $request->integer('page', 1),
            $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => [
                'sessions' => UserSessionResource::collection($page->sessions),
            ],
            'meta' => [
                'page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'has_next_page' => $page->hasNextPage(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $session,
        CurrentIdentity $identity,
        UserSessionRevoker $sessions,
        SecurityMonitor $security,
    ): JsonResponse {
        $userId = $identity->userId($request);

        if (! $sessions->revoke($userId, $session)) {
            // A foreign session looks missing, preventing ownership discovery.
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $security->sessionRevoked($userId);

        return response()->json(['message' => 'Session signed out successfully.']);
    }

    public function destroyAll(
        Request $request,
        CurrentIdentity $identity,
        UserSessionRevoker $sessions,
        SecurityMonitor $security,
    ): JsonResponse {
        $userId = $identity->userId($request);

        $sessions->revokeAll($userId);
        $security->allSessionsRevoked($userId);

        return response()->json(['message' => 'Signed out on every device.']);
    }
}
