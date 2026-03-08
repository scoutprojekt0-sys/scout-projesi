<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateMe']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('players', PlayerController::class)->only(['index', 'show', 'update']);
    Route::apiResource('teams', TeamController::class)->only(['index', 'show', 'update']);
    Route::apiResource('staff', StaffController::class)->only(['index', 'show', 'update']);

    Route::post('/media', [MediaController::class, 'store']);
    Route::get('/users/{id}/media', [MediaController::class, 'indexByUser']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);

    Route::apiResource('opportunities', OpportunityController::class);

    Route::post('/opportunities/{id}/apply', [ApplicationController::class, 'apply']);
    Route::get('/applications/incoming', [ApplicationController::class, 'incoming']);
    Route::get('/applications/outgoing', [ApplicationController::class, 'outgoing']);
    Route::patch('/applications/{id}/status', [ApplicationController::class, 'changeStatus']);

    Route::post('/contacts', [ContactController::class, 'store']);
    Route::get('/contacts/inbox', [ContactController::class, 'inbox']);
    Route::get('/contacts/sent', [ContactController::class, 'sent']);
    Route::patch('/contacts/{id}/status', [ContactController::class, 'changeStatus']);
});
