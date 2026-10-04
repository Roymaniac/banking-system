<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Administration\DepartmentController;
use App\Http\Controllers\Api\V1\Administration\DepartmentDirectoryController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\PermissionDirectoryController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Administration\RoleDirectoryController;
use App\Http\Controllers\Api\V1\Administration\StaffController;
use App\Http\Controllers\Api\V1\Administration\StaffDirectoryController;
use App\Http\Controllers\Api\V1\Audit\ActivityController;
use App\Http\Controllers\Api\V1\Audit\DomainEventController;
use App\Http\Controllers\Api\V1\Audit\SecurityEventController;
use App\Http\Controllers\Api\V1\Authentication\AuthenticationController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerContactController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Ledger\AccountBalanceController;
use App\Http\Controllers\Api\V1\Notification\EmailOutboxController;
use App\Http\Controllers\Api\V1\Operation\TrustedTransactionController;
use App\Http\Controllers\Api\V1\Reporting\CustomerReportController;
use App\Http\Controllers\Api\V1\Reporting\LedgerReportController;
use App\Http\Controllers\Api\V1\Reporting\TransactionReportController;
use App\Http\Controllers\Api\V1\Transaction\DailyTransactionLimitController;
use App\Http\Controllers\Api\V1\Transaction\MultipleTransferController;
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

Route::prefix('v1/operations')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::post('/accounts/{account}/deposits', [TrustedTransactionController::class, 'deposit'])
            ->whereUuid('account')
            ->middleware('permission:transactions.deposit')
            ->name('api.v1.operations.deposits.store');
        Route::post('/accounts/{account}/withdrawals', [TrustedTransactionController::class, 'withdraw'])
            ->whereUuid('account')
            ->middleware('permission:transactions.withdraw')
            ->name('api.v1.operations.withdrawals.store');
        Route::post('/ledger-entries/{entry}/reversals', [TrustedTransactionController::class, 'reverse'])
            ->whereUuid('entry')
            ->middleware('permission:transactions.reverse')
            ->name('api.v1.operations.reversals.store');
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

Route::prefix('v1/administration')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/departments', [DepartmentDirectoryController::class, 'index'])
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.departments.index');
        Route::get('/departments/{department}', [DepartmentDirectoryController::class, 'show'])
            ->whereUuid('department')
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.departments.show');
        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware('permission:departments.manage')
            ->name('api.v1.administration.departments.store');
        Route::patch('/departments/{department}', [DepartmentController::class, 'rename'])
            ->whereUuid('department')
            ->middleware('permission:departments.manage')
            ->name('api.v1.administration.departments.rename');
        Route::delete('/departments/{department}', [DepartmentController::class, 'deactivate'])
            ->whereUuid('department')
            ->middleware('permission:departments.manage')
            ->name('api.v1.administration.departments.deactivate');

        Route::get('/staff', [StaffDirectoryController::class, 'index'])
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.staff.index');
        Route::get('/staff/{staff}', [StaffDirectoryController::class, 'show'])
            ->whereUuid('staff')
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.staff.show');
        Route::post('/staff', [StaffController::class, 'store'])
            ->middleware('permission:staff.manage')
            ->name('api.v1.administration.staff.store');
        Route::patch('/staff/{staff}/department', [StaffController::class, 'transfer'])
            ->whereUuid('staff')
            ->middleware('permission:staff.manage')
            ->name('api.v1.administration.staff.transfer');
        Route::delete('/staff/{staff}', [StaffController::class, 'deactivate'])
            ->whereUuid('staff')
            ->middleware('permission:staff.manage')
            ->name('api.v1.administration.staff.deactivate');

        Route::get('/roles', [RoleDirectoryController::class, 'index'])
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.roles.index');
        Route::get('/roles/{role}', [RoleDirectoryController::class, 'show'])
            ->whereUuid('role')
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.roles.show');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.manage')
            ->name('api.v1.administration.roles.store');
        Route::delete('/roles/{role}', [RoleController::class, 'deactivate'])
            ->whereUuid('role')
            ->middleware('permission:roles.manage')
            ->name('api.v1.administration.roles.deactivate');
        Route::post('/roles/{role}/staff/{staff}', [RoleController::class, 'assignStaff'])
            ->whereUuid(['role', 'staff'])
            ->middleware('permission:roles.manage')
            ->name('api.v1.administration.roles.staff.assign');

        Route::get('/permissions', [PermissionDirectoryController::class, 'index'])
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.permissions.index');
        Route::get('/permissions/{permission}', [PermissionDirectoryController::class, 'show'])
            ->whereUuid('permission')
            ->middleware('permission:administration.view')
            ->name('api.v1.administration.permissions.show');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.manage')
            ->name('api.v1.administration.permissions.store');
        Route::put('/roles/{role}/permissions/{permission}', [PermissionController::class, 'grant'])
            ->whereUuid(['role', 'permission'])
            ->middleware('permission:permissions.manage')
            ->name('api.v1.administration.roles.permissions.grant');
        Route::delete('/roles/{role}/permissions/{permission}', [PermissionController::class, 'revoke'])
            ->whereUuid(['role', 'permission'])
            ->middleware('permission:permissions.manage')
            ->name('api.v1.administration.roles.permissions.revoke');
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
        Route::post('/{account}/multiple-transfers', [MultipleTransferController::class, 'store'])
            ->whereUuid('account')
            ->middleware('throttle:10,1')
            ->name('api.v1.accounts.multiple-transfers.store');
        Route::get('/{account}/daily-limit', [DailyTransactionLimitController::class, 'show'])
            ->whereUuid('account')
            ->name('api.v1.accounts.daily-limit.show');
        Route::patch('/{account}/daily-limit', [DailyTransactionLimitController::class, 'update'])
            ->whereUuid('account')
            ->name('api.v1.accounts.daily-limit.update');
    });

Route::prefix('v1/reports')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/customers/{customer}', [CustomerReportController::class, 'show'])
            ->whereUuid('customer')
            ->middleware('permission:customer_reports.view')
            ->name('api.v1.reports.customers.show');
        Route::get('/ledger', [LedgerReportController::class, 'show'])
            ->middleware('permission:ledger_reports.view')
            ->name('api.v1.reports.ledger.show');
        Route::get('/accounts/{account}/transactions', [TransactionReportController::class, 'show'])
            ->whereUuid('account')
            ->middleware('permission:transaction_reports.view')
            ->name('api.v1.reports.transactions.show');
    });

Route::prefix('v1/audit')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/domain-events', [DomainEventController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('api.v1.audit.domain-events.index');
        Route::get('/activities', [ActivityController::class, 'index'])
            ->middleware('permission:activity.view')
            ->name('api.v1.audit.activities.index');
        Route::get('/security-events', [SecurityEventController::class, 'index'])
            ->middleware('permission:security.view')
            ->name('api.v1.audit.security-events.index');
    });

Route::prefix('v1/notifications')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/outbox', [EmailOutboxController::class, 'index'])
            ->middleware('permission:notifications.view')
            ->name('api.v1.notifications.outbox.index');
        Route::post('/outbox/{message}/retry', [EmailOutboxController::class, 'retry'])
            ->whereUuid('message')
            ->middleware('permission:notifications.retry')
            ->name('api.v1.notifications.outbox.retry');
    });
