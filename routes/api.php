<?php

use App\Http\Controllers\api\attendance\AttendanceController;
use App\Http\Controllers\api\auth\AuthenticationController;
use App\Http\Controllers\DatabaseController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticationController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () { 
    Route::post('/logout', [AuthenticationController::class, 'logout']);
    Route::get('/attendance', [AttendanceController::class, 'attendance']);
});

Route::prefix('databases')->group(function () {
    Route::get('/', [DatabaseController::class, 'index']);
    Route::post('/refresh', [DatabaseController::class, 'refresh']);
    Route::get('/{databaseName}', [DatabaseController::class, 'queryDatabase']);
    Route::get('/{databaseName}/stats', [DatabaseController::class, 'getDatabaseStats']);
    Route::get('/{databaseName}/tables/{tableName}', [DatabaseController::class, 'getTableData']);
    Route::post('/{databaseName}/query', [DatabaseController::class, 'executeQuery']);
    
    // Attendance specific routes
    Route::get('/{databaseName}/attendance', [DatabaseController::class, 'getAttendanceData']);
    Route::patch('/{databaseName}/attendance/{recordId}/read', [DatabaseController::class, 'markAttendanceAsRead']);
    Route::patch('/{databaseName}/attendance/read-all', [DatabaseController::class, 'markAllAttendanceAsRead']);
    
    // Add these Messages specific routes
    Route::get('/{databaseName}/messages', [DatabaseController::class, 'getMessagesData']);
    Route::patch('/{databaseName}/messages/{recordId}/read', [DatabaseController::class, 'markMessageAsRead']);
    Route::patch('/{databaseName}/messages/read-all', [DatabaseController::class, 'markAllMessagesAsRead']);
});