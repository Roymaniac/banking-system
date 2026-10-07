<?php

declare(strict_types=1);

return [
    'rate_limits' => [
        // Login is intentionally strict because password hashing is expensive
        // and repeated attempts may indicate credential guessing.
        'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 6),
        'login_ip_per_minute' => (int) env('RATE_LIMIT_LOGIN_IP_PER_MINUTE', 30),

        // Routine authenticated reads and profile changes are allowed a larger
        // budget while still protecting the application from accidental loops.
        'customer_per_minute' => (int) env('RATE_LIMIT_CUSTOMER_PER_MINUTE', 120),

        // Money movement has a smaller budget because each accepted request can
        // lock balances and write several financial records.
        'money_movement_per_minute' => (int) env('RATE_LIMIT_MONEY_MOVEMENT_PER_MINUTE', 20),

        // Trusted operational and administrative endpoints perform privileged
        // work and should never be used as unbounded bulk APIs.
        'operator_per_minute' => (int) env('RATE_LIMIT_OPERATOR_PER_MINUTE', 60),

        // Reports can execute more expensive read queries than normal screens.
        'reporting_per_minute' => (int) env('RATE_LIMIT_REPORTING_PER_MINUTE', 30),
    ],
];
