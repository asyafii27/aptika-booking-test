<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Master\RoomController;
use App\Http\Controllers\Transaction\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
});

Route::middleware('auth:api')->whereNumber('id')->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
    });

    Route::prefix('rooms')->controller(RoomController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/available', 'available');

        Route::middleware('role:ADMIN')->group(function () {
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
        });
    });

    Route::prefix('bookings')->controller(BookingController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::patch('/{id}/cancel', 'cancel');
    });
});
