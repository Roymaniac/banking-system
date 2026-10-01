<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Authentication\AuthenticationController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerContactController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Ledger\AccountBalanceController;
use App\Http\Controllers\Api\V1\Transaction\TransactionHistoryController;
use App\Http\Controllers\Api\V1\Transaction\TransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthenticationController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthenticationController::class, 'current'])
            ->name('api.v1.auth.me');
        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->name('api.v1.auth.logout');
    });
});

Route::prefix('v1/customer')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/profile', [CustomerProfileController::class, 'show'])
            ->name('api.v1.customer.profile.show');
        Route::post('/profile', [CustomerProfileController::class, 'store'])
            ->name('api.v1.customer.profile.store');

        Route::post('/profile/addresses', [CustomerAddressController::class, 'store'])
            ->name('api.v1.customer.addresses.store');
        Route::put('/profile/addresses/{address}', [CustomerAddressController::class, 'update'])
            ->whereUuid('address')
            ->name('api.v1.customer.addresses.update');

        Route::post('/profile/contacts', [CustomerContactController::class, 'store'])
            ->name('api.v1.customer.contacts.store');
        Route::put('/profile/contacts/{contact}', [CustomerContactController::class, 'update'])
            ->whereUuid('contact')
            ->name('api.v1.customer.contacts.update');
    });

Route::prefix('v1/accounts')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])
            ->name('api.v1.accounts.index');
        Route::post('/', [AccountController::class, 'store'])
            ->name('api.v1.accounts.store');
        Route::get('/{account}', [AccountController::class, 'show'])
            ->whereUuid('account')
            ->name('api.v1.accounts.show');
        Route::get('/{account}/balance', [AccountBalanceController::class, 'show'])
            ->whereUuid('account')
            ->name('api.v1.accounts.balance.show');
        Route::post('/{account}/transfers', [TransferController::class, 'store'])
            ->whereUuid('account')
            ->middleware('throttle:20,1')
            ->name('api.v1.accounts.transfers.store');
        Route::get('/{account}/transactions', [TransactionHistoryController::class, 'index'])
            ->whereUuid('account')
            ->name('api.v1.accounts.transactions.index');
    });
