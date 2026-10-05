<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Shared\Application\Health\SystemHealthCheck;

/** Returns protected readiness details without exposing infrastructure secrets. */
final class SystemHealthController extends Controller
{
    public function show(SystemHealthCheck $health): JsonResponse
    {
        $report = $health->inspect();

        return response()->json([
            'data' => [
                'status' => $report->status,
                'components' => $report->components,
            ],
        ], $report->isReady() ? 200 : 503);
    }
}
