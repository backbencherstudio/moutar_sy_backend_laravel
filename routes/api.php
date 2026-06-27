<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;

// Admin Public Routes
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// user login
Route::post('register', [UserController::class, 'register']);
Route::post('verifyotp', [UserController::class, 'verifyotp']);
Route::post('resetotp', [UserController::class, 'resetotp']);
Route::post('login', [UserController::class, 'login']);

// google login api
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Admin Protected Routes
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::post('/register', [AdminController::class, 'register']);
    Route::get('/index', [AdminController::class, 'index']);
    Route::get('/edit/{id}', [AdminController::class, 'edit']);
    Route::post('/update/{id}', [AdminController::class, 'update']);
    Route::delete('/delete/{id}', [AdminController::class, 'destroy']);

    // Role
    Route::prefix('role')->group(function () {
        Route::get('index', [RoleController::class, 'index'])->name('index');
        Route::post('store', [RoleController::class, 'store'])->name('store');
        Route::get('edit/{id}', [RoleController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [RoleController::class, 'update'])->name('update');
    });
    // permission
    Route::prefix('permission')->group(function () {
        Route::get('index', [PermissionController::class, 'index'])->name('index');
        Route::post('store', [PermissionController::class, 'store'])->name('store');
        Route::get('edit/{id}', [PermissionController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [PermissionController::class, 'update'])->name('update');
    });

    // setting
    Route::prefix('setting')->group(function () {
        Route::get('index', [SettingController::class, 'index']);
        Route::post('update', [SettingController::class, 'update']);
    });

    // mail
    Route::prefix('mail')->group(function () {
        Route::post('/send-email', [EmailController::class, 'sendEmail']);

    });

});
