<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/bookings', [BookingController::class, 'store']);
