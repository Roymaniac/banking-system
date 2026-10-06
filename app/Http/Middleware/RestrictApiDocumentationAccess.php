<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RestrictApiDocumentationAccess
{
    public function __construct(
        private AuthorizationChecker $authorization,
    ) {}

    /**
     * Keep documentation convenient for local development while protecting
     * its operational details everywhere else.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        // Documentation uses the same bearer tokens as the API. Explicitly
        // selecting Sanctum also works when no web-session login exists.
        $user = $request->user('sanctum');

        abort_unless(
            $user instanceof User
                && $user->identity_user_id !== null
                && $this->authorization->allows(
                    new UserId($user->identity_user_id),
                    new Permission('documentation.view'),
                ),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
