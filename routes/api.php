<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Master\RoomController;
use App\Http\Controllers\Transaction\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

Route::middleware('auth:api')->whereNumber('id')->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::get('/me', 'me');
        Route::post('/logout', 'logout');
        Route::post('/refresh', 'refresh');
    });

    Route::prefix('master/rooms')->controller(RoomController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('role:ADMIN')->group(function () {
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });
    });

    Route::prefix('transaction/bookings')->controller(BookingController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::patch('/{id}/cancel', 'cancel');
        Route::delete('/{id}', 'destroy');
    });
});
