<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function configureProductionPreflight(): void
{
    config()->set([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://bank.example.com',
        'notification.verification_url' => 'https://bank.example.com/verify-email',
        'notification.password_reset_url' => 'https://bank.example.com/reset-password',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'mail.default' => 'smtp',
        'banking.settlement_ledgers' => [
            'deposit' => [
                'NGN' => (string) Str::uuid(),
                'USD' => (string) Str::uuid(),
                'GBP' => (string) Str::uuid(),
            ],
            'withdrawal' => [
                'NGN' => (string) Str::uuid(),
                'USD' => (string) Str::uuid(),
                'GBP' => (string) Str::uuid(),
            ],
        ],
    ]);
}

it('passes when production configuration and the database are ready', function (): void {
    configureProductionPreflight();

    $this->artisan('banking:production-preflight')
        ->expectsOutputToContain('Production preflight passed')
        ->assertSuccessful();
});

it('fails safely when production configuration is incomplete', function (): void {
    configureProductionPreflight();
    config()->set([
        'app.debug' => true,
        'app.key' => null,
        'app.url' => 'http://bank.example.com',
        'queue.default' => 'sync',
        'banking.settlement_ledgers.deposit.NGN' => null,
    ]);

    $this->artisan('banking:production-preflight')
        ->expectsOutputToContain('Production preflight failed 5 check(s)')
        ->assertFailed();
});

it('does not print secret configuration values', function (): void {
    configureProductionPreflight();
    $secretKey = 'base64:secret-value-that-must-not-be-printed';
    config()->set('app.key', $secretKey);

    $this->artisan('banking:production-preflight')
        ->doesntExpectOutputToContain($secretKey)
        ->assertSuccessful();
});

it('rejects a settlement ledger reused for another purpose', function (): void {
    configureProductionPreflight();
    $depositLedger = config()->get('banking.settlement_ledgers.deposit.NGN');
    config()->set('banking.settlement_ledgers.withdrawal.NGN', $depositLedger);

    $this->artisan('banking:production-preflight')
        ->expectsOutputToContain('Production preflight failed 1 check(s)')
        ->assertFailed();
});
