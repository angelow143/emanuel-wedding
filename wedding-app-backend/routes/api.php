<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RSVPController;
use App\Http\Controllers\WishController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\GalleryController;

use App\Http\Controllers\SettingController;

use App\Http\Controllers\AlbumController;

// Public Endpoints
Route::get('/rsvps', [RSVPController::class, 'index']);
Route::post('/rsvps', [RSVPController::class, 'store']);
Route::get('/wishes', [WishController::class, 'index']);
Route::post('/wishes', [WishController::class, 'store']);

Route::get('/gallery', [GalleryController::class, 'index']);
Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/settings', [SettingController::class, 'index']); // Public so UI knows login state

// Authenticated Endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);

    // Admin endpoints
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}/password', [AdminUserController::class, 'updatePassword']);
    Route::post('/admin/settings', [SettingController::class, 'update']);

    // Standard & Admin auth user endpoint
    Route::post('/gallery', [GalleryController::class, 'store']);
    Route::delete('/gallery/{gallery}', [GalleryController::class, 'destroy']);
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::put('/albums/{album}', [AlbumController::class, 'update']);
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy']);
    Route::delete('/wishes/{wish}', [WishController::class, 'destroy']);
    Route::patch('/wishes/{wish}/toggle-visibility', [WishController::class, 'toggleVisibility']);
});

Route::get('/debug-log', function() { return file_exists(storage_path('logs/laravel.log')) ? file_get_contents(storage_path('logs/laravel.log')) : 'No log file'; });
