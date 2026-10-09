<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ChangeMoneyMovementStatusRequest;
use App\Http\Requests\Api\V1\Operation\ListMoneyMovementControlEventsRequest;
use App\Http\Requests\Api\V1\Operation\ListMoneyMovementResumeRequestsRequest;
use App\Http\Resources\Api\V1\Operation\MoneyMovementControlEventResource;
use App\Http\Resources\Api\V1\Operation\MoneyMovementResumeRequestResource;
use App\Http\Resources\Api\V1\Operation\MoneyMovementStatusResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementControlEventQuery;
use Transaction\Application\Control\MoneyMovementResumeApproval;
use Transaction\Application\Control\MoneyMovementResumeRequestQuery;

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
        MoneyMovementResumeApproval $approval,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $resumeRequest = $approval->request(
            $request->string('reason')->toString(),
            $currentIdentity->userId($request),
        );

        return response()->json([
            'data' => [
                'resume_request' => new MoneyMovementResumeRequestResource($resumeRequest),
            ],
        ], 202);
    }

    public function resumeRequests(
        ListMoneyMovementResumeRequestsRequest $request,
        MoneyMovementResumeRequestQuery $resumeRequests,
    ): JsonResponse {
        $result = $resumeRequests->list(
            $request->filters(),
            $request->pageNumber(),
            $request->pageSize(),
        );

        return response()->json([
            'data' => [
                'resume_requests' => MoneyMovementResumeRequestResource::collection($result['items']),
            ],
            'meta' => $result['pagination'],
        ]);
    }

    public function approveResume(
        Request $request,
        string $resumeRequest,
        MoneyMovementResumeApproval $approval,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $approved = $approval->approve(
            new Uuid($resumeRequest),
            $currentIdentity->userId($request),
        );

        return response()->json([
            'data' => [
                'resume_request' => new MoneyMovementResumeRequestResource($approved),
            ],
        ]);
    }

    public function rejectResume(
        ChangeMoneyMovementStatusRequest $request,
        string $resumeRequest,
        MoneyMovementResumeApproval $approval,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $rejected = $approval->reject(
            new Uuid($resumeRequest),
            $currentIdentity->userId($request),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'data' => [
                'resume_request' => new MoneyMovementResumeRequestResource($rejected),
            ],
        ]);
    }

    public function cancelResume(
        ChangeMoneyMovementStatusRequest $request,
        string $resumeRequest,
        MoneyMovementResumeApproval $approval,
        CurrentCustomer $currentIdentity,
    ): JsonResponse {
        $cancelled = $approval->cancel(
            new Uuid($resumeRequest),
            $currentIdentity->userId($request),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'data' => [
                'resume_request' => new MoneyMovementResumeRequestResource($cancelled),
            ],
        ]);
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
