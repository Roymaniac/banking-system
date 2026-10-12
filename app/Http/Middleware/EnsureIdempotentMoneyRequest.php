<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use DateInterval;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use JsonException;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\UuidGenerator;
use Symfony\Component\HttpFoundation\Response;

/** Prevents a retried money request from applying the same operation twice. */
final readonly class EnsureIdempotentMoneyRequest
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAY_HEADER = 'Idempotency-Replayed';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);

        if (! is_string($key) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,99}$/', $key) !== 1) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required and must contain 8 to 100 safe characters.',
            ], 422);
        }

        $user = $request->user();
        $route = $request->route()?->getName();

        if (! $user instanceof User || $user->identity_user_id === null || ! is_string($route)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $actorId = $user->identity_user_id;
        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($request);

        return $this->connection->transaction(function () use (
            $request,
            $next,
            $actorId,
            $route,
            $keyHash,
            $requestHash,
        ): Response {
            $now = $this->clock->now();
            $inserted = $this->connection->table('api_idempotency_records')->insertOrIgnore([
                'id' => $this->uuidGenerator->generate()->value(),
                'actor_id' => $actorId,
                'route' => $route,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'state' => 'processing',
                'created_at' => $now,
                'expires_at' => $now->add(new DateInterval(sprintf(
                    'PT%dH',
                    max(1, (int) config('banking.idempotency.retention_hours', 24)),
                ))),
            ]);

            $record = $this->connection->table('api_idempotency_records')
                ->where('actor_id', $actorId)
                ->where('route', $route)
                ->where('key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new \RuntimeException('The idempotency record could not be loaded.');
            }

            if (! hash_equals((string) $record->request_hash, $requestHash)) {
                return response()->json([
                    'message' => 'This idempotency key was already used with different request data.',
                ], 409);
            }

            if ((string) $record->state === 'completed') {
                return response((string) $record->response_body, (int) $record->response_status, [
                    'Content-Type' => (string) $record->content_type,
                    self::REPLAY_HEADER => 'true',
                ]);
            }

            // An existing processing row is only possible after manual recovery or
            // an unusual database intervention because failed transactions roll back.
            if ($inserted === 0) {
                return response()->json([
                    'message' => 'This idempotent request is still being processed. Retry shortly.',
                ], 409, ['Retry-After' => '1']);
            }

            $response = $next($request);

            // A 202 response means another security step is still required. It
            // must not become the final replay result for this money request.
            if (
                $response->getStatusCode() < 200
                || $response->getStatusCode() >= 300
                || $response->getStatusCode() === Response::HTTP_ACCEPTED
            ) {
                $this->connection->table('api_idempotency_records')
                    ->where('id', $record->id)
                    ->delete();

                return $response;
            }

            $body = $response->getContent();
            $contentType = $response->headers->get('Content-Type', 'application/json');

            $this->connection->table('api_idempotency_records')
                ->where('id', $record->id)
                ->update([
                    'state' => 'completed',
                    'response_status' => $response->getStatusCode(),
                    'response_body' => is_string($body) ? $body : '',
                    'content_type' => $contentType,
                    'completed_at' => $now,
                ]);

            $response->headers->set(self::REPLAY_HEADER, 'false');

            return $response;
        });
    }

    /** @throws JsonException */
    private function requestHash(Request $request): string
    {
        $fingerprint = [
            'method' => $request->getMethod(),
            'route_parameters' => $this->normalize($request->route()?->parameters() ?? []),
            'query' => $this->normalize($request->query->all()),
            'body' => $this->normalize($request->all()),
        ];

        return hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
    }

    /** Sorts object-like arrays while preserving the order of real lists. */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
