<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Authentication\AuthenticationController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
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
    });
