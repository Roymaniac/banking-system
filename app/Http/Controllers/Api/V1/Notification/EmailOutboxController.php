<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\ListEmailOutboxRequest;
use Illuminate\Http\JsonResponse;
use Notification\Application\Outbox\EmailOutboxQuery;
use Notification\Application\Outbox\Exception\EmailOutboxMessageCannotRetry;
use Notification\Application\Outbox\Exception\EmailOutboxMessageNotFound;
use Notification\Application\Outbox\RetryExhaustedEmail;
use Shared\Domain\Identifier\Uuid;

/** Shows safe email-delivery health metadata to authorized operators. */
final class EmailOutboxController extends Controller
{
    public function index(
        ListEmailOutboxRequest $request,
        EmailOutboxQuery $outbox,
    ): JsonResponse {

        $result = $outbox->search(
            $request->filters(),
            $request->pageNumber(),
            $request->pageSize(),
        );

        return response()->json([
            'data' => [
                'messages' => $result['items'],
                'summary' => $result['summary'],
            ],
            'meta' => $result['pagination'],
        ]);
    }

    public function retry(string $message, RetryExhaustedEmail $retry): JsonResponse
    {
        try {
            $retry->handle(new Uuid($message));
        } catch (EmailOutboxMessageNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (EmailOutboxMessageCannotRetry $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Email requeued for delivery successfully.',
        ], 202);
    }
}
