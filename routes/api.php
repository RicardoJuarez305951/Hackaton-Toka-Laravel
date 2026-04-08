<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\GameController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::prefix('user/{userId}')->group(function () {
    Route::get('/balance', [GameController::class, 'balance']);
    Route::get('/streak', [GameController::class, 'streak']);
    Route::get('/history', [GameController::class, 'history']);
});

Route::prefix('rasca')->group(function () {
    Route::post('/play', [GameController::class, 'playRasca']);
});

Route::prefix('plinko')->group(function () {
    Route::post('/play', [GameController::class, 'playPlinko']);
});

Route::prefix('ruleta')->group(function () {
    Route::post('/play', [GameController::class, 'playRuleta']);
});

Route::prefix('debug')->group(function () {
    Route::post('/coins', [DebugController::class, 'addCoins']);
});
