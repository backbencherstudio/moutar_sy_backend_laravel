<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Frontend\UserController;
use Illuminate\Support\Facades\Route;

// Admin Public Routes
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// user login
Route::post('/user/register', [UserController::class, 'register'])->name('user.register');
Route::post('/user/login', [UserController::class, 'login'])->name('user.login');
Route::middleware('auth:sanctum')->post('/user/logout', [UserController::class, 'logout']);

// google login api
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Admin Protected Routes
Route::middleware(['auth:sanctum'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/register', [AdminController::class, 'register'])->name('register');
    Route::get('/index', [AdminController::class, 'index'])->name('index');
    Route::get('/edit/{id}', [AdminController::class, 'edit'])->name('edit');
    Route::post('/update/{id}', [AdminController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [AdminController::class, 'destroy'])->name('destroy');

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
    // category
    Route::prefix('category')->group(function () {
        Route::get('index', [CategoryController::class, 'index'])->name('index');
        Route::post('store', [CategoryController::class, 'store'])->name('store');
        Route::get('edit/{id}', [CategoryController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [CategoryController::class, 'destroy'])->name('destroy');
    });
    // subcategory
    Route::prefix('subcategory')->group(function () {
        Route::get('index', [SubcategoryController::class, 'index'])->name('index');
        Route::post('store', [SubcategoryController::class, 'store'])->name('store');
        Route::get('edit/{id}', [SubcategoryController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [SubcategoryController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [SubcategoryController::class, 'destroy'])->name('destroy');
    });
    // Brand
    Route::prefix('brand')->group(function () {
        Route::get('index', [BrandController::class, 'index'])->name('index');
        Route::post('store', [BrandController::class, 'store'])->name('store');
        Route::get('edit/{id}', [BrandController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [BrandController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [BrandController::class, 'destroy'])->name('destroy');
    });
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
