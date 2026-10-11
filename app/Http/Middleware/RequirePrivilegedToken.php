<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Identity\Application\Security\SecurityMonitor;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Requires a recently password-confirmed staff token for protected operations. */
final readonly class RequirePrivilegedToken
{
    public function __construct(private SecurityMonitor $security) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->tokenCan('privileged')) {
            return $next($request);
        }

        if ($user instanceof User && $user->identity_user_id !== null) {
            $this->security->privilegedAccessDenied(new UserId($user->identity_user_id));
        }

        return response()->json([
            'message' => 'A recent privileged staff sign-in is required for this action.',
        ], 403);
    }
}
