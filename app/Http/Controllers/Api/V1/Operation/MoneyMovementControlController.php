<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ChangeMoneyMovementStatusRequest;
use App\Http\Resources\Api\V1\Operation\MoneyMovementStatusResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Illuminate\Http\JsonResponse;
use Transaction\Application\Control\MoneyMovementControl;

/** Provides permission-protected operator access to the global safety switch. */
final class MoneyMovementControlController extends Controller
{
    public function show(MoneyMovementControl $control): JsonResponse
    {
        return $this->response($control);
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
