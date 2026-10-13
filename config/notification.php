<?php

declare(strict_types=1);

return [
    // Keep customer-facing URLs configurable because the web application may
    // be hosted separately from this API.
    'verification_url' => env('FRONTEND_VERIFICATION_URL', rtrim((string) env('FRONTEND_CUSTOMER_URL', 'http://127.0.0.1:5173'), '/') . '/verify-email'),
    'password_reset_url' => env('FRONTEND_PASSWORD_RESET_URL', rtrim((string) env('FRONTEND_CUSTOMER_URL', 'http://127.0.0.1:5173'), '/') . '/reset-password'),
    // This page must require operator authentication and approval permission.
    'operations_url' => env('OPERATIONS_PORTAL_URL', rtrim((string) env('FRONTEND_OPERATIONS_URL', 'http://127.0.1:5174'), '/') . '/operations/money-movement/resume-requests'),
    'risk_cases_url' => env('RISK_CASES_PORTAL_URL', rtrim((string) env('FRONTEND_OPERATIONS_URL', 'http://127.0.1:5174'), '/') . '/operations/transfer-risk-cases'),
];
