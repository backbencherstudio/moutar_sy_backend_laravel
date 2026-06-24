<?php

use App\Http\Controllers\Admin\AdminController;

use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Frontend\UserController;
use Illuminate\Support\Facades\Route;

// Admin Public Routes
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// user login
Route::post('/user/register', [UserController::class, 'register'])->name('user.register');
Route::post('/user/login', [UserController::class, 'login'])->name('user.login');
Route::middleware('auth:admin')->post('/user/logout', [UserController::class, 'logout']);

// google login api
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Admin Protected Routes
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/register', [AdminController::class, 'register'])->name('register');
    Route::get('/index', [AdminController::class, 'index'])->name('index');
    Route::get('/edit/{id}', [AdminController::class, 'edit'])->name('edit');
    Route::post('/update/{id}', [AdminController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [AdminController::class, 'destroy'])->name('destroy');



    // setting
    Route::prefix('setting')->group(function () {
        Route::get('index', [SettingController::class, 'index'])->name('index');
        Route::post('update', [SettingController::class, 'update'])->name('update');
    });

    // mail
    Route::prefix('mail')->group(function () {
        Route::post('/send-email', [EmailController::class, 'sendEmail']);

    });

});
