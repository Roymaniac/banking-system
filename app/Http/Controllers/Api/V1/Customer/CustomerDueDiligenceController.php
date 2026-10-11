<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\SubmitDueDiligenceRequest;
use App\Http\Resources\Api\V1\Compliance\DueDiligenceResource;
use App\Http\Support\Api\V1\CurrentCustomer;
use Compliance\Application\DueDiligence\DueDiligenceGateway;
use Compliance\Domain\DueDiligence\DocumentType;
use Compliance\Domain\DueDiligence\Exception\DuplicateIdentityDocument;
use Compliance\Domain\DueDiligence\Exception\InvalidDueDiligenceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Contracts\Clock;

/** Lets a customer safely submit and inspect their own identity review. */
final class CustomerDueDiligenceController extends Controller
{
    public function show(
        Request $request,
        CurrentCustomer $currentCustomer,
        DueDiligenceGateway $dueDiligence,
    ): JsonResponse {
        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        $case = $dueDiligence->findForCustomer($customer->id());

        return response()->json([
            'data' => ['due_diligence' => $case === null ? null : new DueDiligenceResource($case)],
        ]);
    }

    public function submit(
        SubmitDueDiligenceRequest $request,
        CurrentCustomer $currentCustomer,
        DueDiligenceGateway $dueDiligence,
        Clock $clock,
    ): JsonResponse {
        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        try {
            $case = $dueDiligence->submit(
                $customer->id(),
                $currentCustomer->userId($request),
                DocumentType::from($request->string('document_type')->toString()),
                $request->string('issuing_country')->toString(),
                $request->string('document_number')->toString(),
                $clock->now(),
            );
        } catch (DuplicateIdentityDocument|InvalidDueDiligenceAction $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => ['due_diligence' => new DueDiligenceResource($case)],
        ], 202);
    }
}
