<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ChangeMoneyMovementStatusRequest;
use App\Http\Requests\Api\V1\Operation\ListMoneyMovementControlEventsRequest;
use App\Http\Resources\Api\V1\Operation\MoneyMovementControlEventResource;
use App\Http\Resources\Api\V1\Operation\MoneyMovementStatusResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Illuminate\Http\JsonResponse;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementControlEventQuery;

/** Provides permission-protected operator access to the global safety switch. */
final class MoneyMovementControlController extends Controller
{
    public function show(MoneyMovementControl $control): JsonResponse
    {
        return $this->response($control);
    }

    public function events(
        ListMoneyMovementControlEventsRequest $request,
        MoneyMovementControlEventQuery $events,
    ): JsonResponse {
        $result = $events->list(
            $request->filters(),
            $request->pageNumber(),
            $request->pageSize(),
        );

        return response()->json([
            'data' => [
                'events' => MoneyMovementControlEventResource::collection($result['items']),
            ],
            'meta' => $result['pagination'],
        ]);
    }

    public function suspend(
        ChangeMoneyMovementStatusRequest $request,
        MoneyMovementControl $control,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $control->suspend(
            $request->string('reason')->toString(),
            'operator_api',
            $currentIdentity->userId($request),
        );

        return $this->response($control);
    }

    public function resume(
        ChangeMoneyMovementStatusRequest $request,
        MoneyMovementControl $control,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $control->resume(
            $request->string('reason')->toString(),
            'operator_api',
            $currentIdentity->userId($request),
        );

        return $this->response($control);
    }

    private function response(MoneyMovementControl $control): JsonResponse
    {
        return response()->json([
            'data' => [
                'money_movement' => new MoneyMovementStatusResource($control->current()),
            ],
        ]);
    }
}
