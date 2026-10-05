<?php

declare(strict_types=1);

return [
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
