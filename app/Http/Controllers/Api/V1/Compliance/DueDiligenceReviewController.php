<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\ApproveDueDiligenceRequest;
use App\Http\Requests\Api\V1\Compliance\ListDueDiligenceRequest;
use App\Http\Requests\Api\V1\Compliance\RejectDueDiligenceRequest;
use App\Http\Resources\Api\V1\Compliance\DueDiligenceResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Compliance\Application\DueDiligence\DueDiligenceGateway;
use Compliance\Domain\DueDiligence\Exception\InvalidDueDiligenceAction;
use Compliance\Domain\DueDiligence\RiskLevel;
use Illuminate\Http\JsonResponse;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;

/** Gives authorized compliance staff a bounded four-eyes review workflow. */
final class DueDiligenceReviewController extends Controller
{
    public function index(ListDueDiligenceRequest $request, DueDiligenceGateway $dueDiligence): JsonResponse
    {
        $result = $dueDiligence->reviewQueue(
            $request->integer('page', 1),
            $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => ['cases' => DueDiligenceResource::collection($result['items'])],
            'meta' => $result['pagination'],
        ]);
    }

    public function approve(
        ApproveDueDiligenceRequest $request,
        string $case,
        DueDiligenceGateway $dueDiligence,
        CurrentCustomer $currentIdentity,
        Clock $clock,
    ): JsonResponse {
        try {
            $reviewed = $dueDiligence->approve(
                new Uuid($case),
                $currentIdentity->userId($request),
                RiskLevel::from($request->string('risk_level')->toString()),
                $clock->now(),
            );
        } catch (InvalidDueDiligenceAction $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['due_diligence' => new DueDiligenceResource($reviewed)]]);
    }

    public function reject(
        RejectDueDiligenceRequest $request,
        string $case,
        DueDiligenceGateway $dueDiligence,
        CurrentCustomer $currentIdentity,
        Clock $clock,
    ): JsonResponse {
        try {
            $reviewed = $dueDiligence->reject(
                new Uuid($case),
                $currentIdentity->userId($request),
                $request->string('reason')->toString(),
                $clock->now(),
            );
        } catch (InvalidDueDiligenceAction $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['due_diligence' => new DueDiligenceResource($reviewed)]]);
    }
}
