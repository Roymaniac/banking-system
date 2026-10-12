<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operation\ListTransferRiskCasesRequest;
use App\Http\Requests\Api\V1\Operation\ResolveTransferRiskCaseRequest;
use App\Http\Resources\Api\V1\Operation\TransferRiskCaseResource;
use App\Http\Support\Api\V1\CurrentIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Risk\TransferRiskCaseQuery;
use Transaction\Application\Risk\TransferRiskCaseReview;
use Transaction\Domain\Risk\Exception\InvalidTransferRiskCaseTransition;
use Transaction\Domain\Risk\Exception\TransferRiskCaseNotFound;
use Transaction\Domain\Risk\TransferRiskResolution;

/** Gives authorized risk staff a bounded, accountable review workflow. */
final class TransferRiskCaseController extends Controller
{
    public function index(
        ListTransferRiskCasesRequest $request,
        TransferRiskCaseQuery $cases,
    ): JsonResponse {
        $result = $cases->list($request->filters(), $request->pageNumber(), $request->pageSize());

        return response()->json([
            'data' => ['risk_cases' => TransferRiskCaseResource::collection($result['items'])],
            'meta' => $result['pagination'],
        ]);
    }

    public function claim(
        Request $request,
        string $riskCase,
        TransferRiskCaseReview $review,
        CurrentIdentity $identity,
    ): JsonResponse {
        try {
            $claimed = $review->claim(new Uuid($riskCase), $identity->userId($request));
        } catch (TransferRiskCaseNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidTransferRiskCaseTransition $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['risk_case' => new TransferRiskCaseResource($claimed)]]);
    }

    public function resolve(
        ResolveTransferRiskCaseRequest $request,
        string $riskCase,
        TransferRiskCaseReview $review,
        CurrentIdentity $identity,
    ): JsonResponse {
        try {
            $resolved = $review->resolve(
                new Uuid($riskCase),
                $identity->userId($request),
                TransferRiskResolution::from($request->string('resolution')->toString()),
                $request->string('note')->toString(),
            );
        } catch (TransferRiskCaseNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidTransferRiskCaseTransition $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['risk_case' => new TransferRiskCaseResource($resolved)]]);
    }
}
