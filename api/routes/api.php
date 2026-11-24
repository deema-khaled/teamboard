<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ColumnController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });
    Route::get('invitations/{token}', [MemberController::class, 'showInvitation'])->middleware('throttle:30,1');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('boards', BoardController::class);
        Route::get('boards/{board}/activity', [BoardController::class, 'activity']);

        Route::post('boards/{board}/columns', [ColumnController::class, 'store']);
        Route::put('boards/{board}/columns/order', [ColumnController::class, 'reorder']);
        Route::patch('columns/{column}', [ColumnController::class, 'update']);
        Route::delete('columns/{column}', [ColumnController::class, 'destroy']);

        Route::post('columns/{column}/cards', [CardController::class, 'store']);
        Route::get('cards/{card}', [CardController::class, 'show']);
        Route::patch('cards/{card}', [CardController::class, 'update']);
        Route::patch('cards/{card}/move', [CardController::class, 'move']);
        Route::delete('cards/{card}', [CardController::class, 'destroy']);

        Route::post('cards/{card}/comments', [CommentController::class, 'store']);
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

        Route::post('boards/{board}/invitations', [MemberController::class, 'invite'])->middleware('throttle:20,1');
        Route::post('invitations/{token}/accept', [MemberController::class, 'accept']);
        Route::delete('boards/{board}/members/{user}', [MemberController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    });
});
