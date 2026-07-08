<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\User\BeneficiaryController;
use App\Http\Controllers\User\KycController;
use Illuminate\Support\Facades\Route;

// Admin Public Routes
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// user login
Route::post('register', [UserController::class, 'register']);
Route::post('verifyotp', [UserController::class, 'verifyOtp']);
Route::post('resetotp', [UserController::class, 'resetOtp']);
Route::post('login', [UserController::class, 'login']);
Route::post('loginverify', [UserController::class, 'loginVerify']);

// google login api
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Admin Protected Routes
// Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
Route::middleware(['auth:api'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/register', [AdminController::class, 'register'])->name('register');
    Route::get('/index', [AdminController::class, 'index'])->name('index');
    Route::get('/edit/{id}', [AdminController::class, 'edit'])->name('edit');
    Route::post('/update/{id}', [AdminController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [AdminController::class, 'destroy'])->name('destroy');

    // Role
    Route::prefix('role')->name('role.')->group(function () {
        Route::get('index', [RoleController::class, 'index'])->name('index');
        Route::post('store', [RoleController::class, 'store'])->name('store');
        Route::get('edit/{id}', [RoleController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [RoleController::class, 'update'])->name('update');
    });
    // permission
    Route::prefix('permission')->name('permission.')->group(function () {
        Route::get('index', [PermissionController::class, 'index'])->name('index');
        Route::post('store', [PermissionController::class, 'store'])->name('store');
        Route::get('edit/{id}', [PermissionController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [PermissionController::class, 'update'])->name('update');
    });
    // exchange rate
    Route::prefix('exchange')->name('exchange.')->group(function () {
        Route::get('index', [ExchangeRateController::class, 'index'])->name('index');
        Route::post('calculate', [ExchangeRateController::class, 'calculate'])->name('calculate');
        Route::post('store', [ExchangeRateController::class, 'store'])->name('store');
        Route::get('edit/{id}', [ExchangeRateController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [ExchangeRateController::class, 'update'])->name('update');
        Route::delete('delete/{id}', [ExchangeRateController::class, 'destroy'])->name('destroy');
    });

    // setting
    Route::prefix('setting')->name('setting.')->group(function () {
        Route::get('index', [SettingController::class, 'index'])->name('index');
        Route::post('update', [SettingController::class, 'update'])->name('update');
    });

    // mail
    Route::prefix('mail')->group(function () {
        Route::post('/send-email', [EmailController::class, 'sendEmail']);

    });

});

Route::middleware(['auth:user-api'])->prefix('user')->group(function () {

    Route::prefix('beneficiaries')->name('beneficiaries.')->group(function () {
        Route::post('store', [BeneficiaryController::class, 'store'])->name('store');
        Route::post('verify-otp', [BeneficiaryController::class, 'verifyOtp'])->name('verify-otp');

    });
    Route::prefix('kyc')->name('kyc.')->group(function () {
        Route::get('index', [KycController::class, 'index'])->name('index');
        Route::post('initiate', [KycController::class, 'createSession'])->name('initiate');
        Route::get('sync-status', [KycController::class, 'checkAndSyncKycStatus'])->name('sync-status');
    });

});


Route::match(['get', 'post'], '/webhooks/didit', [KycController::class, 'initiateVerification'])->name('didit.webhook');
