<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\CurrentCustomer;
use Closure;
use Identity\Application\Authorization\AuthorizeUser;
use Identity\Application\Authorization\AuthorizeUserCommand;
use Identity\Domain\Authorization\Exception\AccessDenied;
use Identity\Domain\Authorization\ValueObject\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Enforces one named permission and records denied attempts through Identity. */
final readonly class RequirePermission
{
    public function __construct(
        private AuthorizeUser $authorizeUser,
        private CurrentCustomer $currentIdentity,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {

        try {
            $this->authorizeUser->handle(
                new AuthorizeUserCommand(
                    $this->currentIdentity->userId($request),
                    new Permission($permission),
                )
            );
        } catch (AccessDenied $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 403);
        }

        return $next($request);
    }
}
