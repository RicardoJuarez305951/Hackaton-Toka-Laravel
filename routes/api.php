<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PlinkoController;
use App\Http\Controllers\Api\RascaController;
use App\Http\Controllers\Api\RuletaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/authenticate', [AuthController::class, 'authenticate']);
});

Route::prefix('user/{userId}')->group(function () {
    Route::get('/balance', [GameController::class, 'balance']);
    Route::get('/streak', [GameController::class, 'streak']);
    Route::get('/history', [GameController::class, 'history']);
});

Route::prefix('rasca')->group(function () {
    Route::post('/play', [RascaController::class, 'play']);
});

Route::prefix('plinko')->group(function () {
    Route::post('/play', [PlinkoController::class, 'play']);
});

Route::prefix('ruleta')->group(function () {
    Route::post('/play', [RuletaController::class, 'play']);
});

Route::prefix('debug')->group(function () {
    Route::post('/coins', [DebugController::class, 'addCoins']);
});
