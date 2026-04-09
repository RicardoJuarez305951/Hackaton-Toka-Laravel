<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GoldenTreeController;
use App\Http\Controllers\Api\HiloController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlinkoController;
use App\Http\Controllers\Api\RascaController;
use App\Http\Controllers\Api\RuletaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/authenticate', [AuthController::class, 'authenticate']);
});

Route::prefix('payment')->group(function () {
    Route::post('/create', [PaymentController::class, 'create']);
    Route::post('/close', [PaymentController::class, 'close']);
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

Route::prefix('hilo')->group(function () {
    Route::post('/start', [HiloController::class, 'start']);
    Route::post('/guess', [HiloController::class, 'guess']);
    Route::post('/cashout', [HiloController::class, 'cashout']);
});

Route::prefix('goldentree')->group(function () {
    Route::get('/state', [GoldenTreeController::class, 'state']);
    Route::post('/collect', [GoldenTreeController::class, 'collect']);
    Route::post('/water', [GoldenTreeController::class, 'water']);
    Route::post('/resolve-event', [GoldenTreeController::class, 'resolveEvent']);
});

Route::prefix('debug')->group(function () {
    Route::post('/coins', [DebugController::class, 'addCoins']);
});
