<?php

declare(strict_types=1);

return [
    // Keep customer-facing URLs configurable because the web application may
    // be hosted separately from this API.
    'verification_url' => rtrim((string) env('CUSTOMER_PORTAL_URL', 'http://127.0.0.1:5173'), '/').'/verify-email',
    'password_reset_url' => rtrim((string) env('CUSTOMER_PORTAL_URL', 'http://127.0.0.1:5173'), '/').'/reset-password',
    // This page must require operator authentication and approval permission.
    'operations_url' => rtrim((string) env('OPERATIONS_PORTAL_URL', 'http://127.0.0.1:5174'), '/').'/operations/money-movement/resume-requests',
    'risk_cases_url' => rtrim((string) env('OPERATIONS_PORTAL_URL', 'http://127.0.0.1:5174'), '/').'/operations/transfer-risk-cases',
];
