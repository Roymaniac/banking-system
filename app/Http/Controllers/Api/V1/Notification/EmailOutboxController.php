<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\ListEmailOutboxRequest;
use Illuminate\Http\JsonResponse;
use Notification\Application\Outbox\EmailOutboxQuery;

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
}
