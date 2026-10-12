<?php

declare(strict_types=1);

return [
    'privileged_access' => [
        // Staff must confirm their password again after this short window.
        'minutes' => (int) env('PRIVILEGED_ACCESS_MINUTES', 15),
    ],

    'high_value_transfer' => [
        // Transfers at or above this amount require a fresh MFA approval.
        'threshold_minor_units' => (int) env('HIGH_VALUE_TRANSFER_THRESHOLD_MINOR_UNITS', 1000000),
        // Approval tokens are intentionally brief and work for one exact request.
        'approval_minutes' => (int) env('HIGH_VALUE_TRANSFER_APPROVAL_MINUTES', 5),
    ],

    'transfer_risk' => [
        // A burst of individually valid transfers can still signal account takeover.
        'velocity_window_minutes' => (int) env('TRANSFER_RISK_WINDOW_MINUTES', 60),
        'maximum_transfers' => (int) env('TRANSFER_RISK_MAXIMUM_TRANSFERS', 10),
    ],

    'rate_limits' => [
        // Login is intentionally strict because password hashing is expensive
        // and repeated attempts may indicate credential guessing.
        'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 6),
        'login_ip_per_minute' => (int) env('RATE_LIMIT_LOGIN_IP_PER_MINUTE', 30),

        'privileged_auth_per_minute' => (int) env('RATE_LIMIT_PRIVILEGED_AUTH_PER_MINUTE', 5),

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
