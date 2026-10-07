<?php

declare(strict_types=1);

return [
    'request_logging' => [
        // Completion logs contain route names, status, timing, and request IDs.
        // They deliberately omit URLs, request bodies, and route parameters.
        'enabled' => (bool) env('OBSERVABILITY_REQUEST_LOGS', true),

        // Container health probes run frequently and provide little diagnostic
        // value when successful, so they are quiet by default.
        'exclude_liveness' => (bool) env('OBSERVABILITY_EXCLUDE_LIVENESS_LOGS', true),
    ],
];
