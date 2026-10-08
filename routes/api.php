<?php

use App\Http\Controllers\Api\V1\AllocationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Authentication
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Protected Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // Authenticated Session
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Department Management
        Route::apiResource('departments', DepartmentController::class);

        // Employee Management
        Route::apiResource('employees', EmployeeController::class)->only(['index', 'show', 'update']);

        // Project Management
        Route::apiResource('projects', ProjectController::class);

        // Task Management
        Route::apiResource('tasks', TaskController::class);

        // Employee Workload Allocations
        Route::apiResource('allocations', AllocationController::class);
    });
});
