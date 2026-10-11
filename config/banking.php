<?php

declare(strict_types=1);

return [
    // Clients may safely retry a successful money request during this window.
    'idempotency' => [
        'retention_hours' => env('IDEMPOTENCY_RETENTION_HOURS', 24),
    ],

    // These UUIDs belong to internal ledgers and must never come from API input.
    'settlement_ledgers' => [
        'deposit' => [
            'NGN' => env('BANKING_NGN_DEPOSIT_LEDGER_ID'),
            'USD' => env('BANKING_USD_DEPOSIT_LEDGER_ID'),
            'GBP' => env('BANKING_GBP_DEPOSIT_LEDGER_ID'),
        ],
        'withdrawal' => [
            'NGN' => env('BANKING_NGN_WITHDRAWAL_LEDGER_ID'),
            'USD' => env('BANKING_USD_WITHDRAWAL_LEDGER_ID'),
            'GBP' => env('BANKING_GBP_WITHDRAWAL_LEDGER_ID'),
        ],
    ],
];
