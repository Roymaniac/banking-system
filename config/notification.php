<?php

declare(strict_types=1);

return [
    // Keep customer-facing URLs configurable because the web application may
    // be hosted separately from this API.
    'verification_url' => env('FRONTEND_VERIFICATION_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/verify-email'),
    'password_reset_url' => env('FRONTEND_PASSWORD_RESET_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/reset-password'),
];
