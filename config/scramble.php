<?php

declare(strict_types=1);

use App\Http\Middleware\RestrictApiDocumentationAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    // Only the stable, versioned API is part of the public contract.
    'api_path' => 'api/v1',

    'api_domain' => null,
    'export_path' => 'openapi.json',

    'cache' => [
        'key' => 'scramble.openapi.v1',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MARKDOWN'
The version 1 banking API.

Protected endpoints expect a Laravel Sanctum bearer token. Money values are sent as integer minor units—for example, `1050` means 10.50 in a two-decimal currency. Date and time values use ISO 8601.

Administrative, operational, reporting, audit, and notification endpoints require a short-lived privileged bearer token obtained from `/auth/privileged-access` after an active staff member confirms their password.

Error responses contain a human-readable `message`. Validation failures also contain an `errors` object keyed by input field.

Money-changing endpoints require an `Idempotency-Key` header. Retrying the same route and body with the same key returns the original successful response without applying the financial operation again. Reusing a key with different instructions returns HTTP 409.
MARKDOWN,
    ],

    'ui' => [
        'title' => 'Banking System API',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => null,
    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictApiDocumentationAccess::class,
    ],

    'extensions' => [],

    // Scramble reads auth:sanctum middleware and documents bearer-token security.
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
