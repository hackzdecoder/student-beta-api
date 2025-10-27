<?php

use App\Http\Controllers\api\attendance\AttendanceController;
use App\Http\Controllers\api\auth\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticationController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () { 
    Route::post('/logout', [AuthenticationController::class, 'logout']);
    Route::get('/attendance', [AttendanceController::class, 'attendance']);
});
