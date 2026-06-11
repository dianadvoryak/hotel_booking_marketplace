<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\HotelController;
use Illuminate\Support\Facades\Route;

Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/hotels/popular', [HotelController::class, 'index']);
Route::post('/bookings/hold', [BookingController::class, 'hold']);
