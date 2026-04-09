<?php

use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\GameViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameViewController::class, 'index']);

Route::get('/rasca', [GameViewController::class, 'rasca']);
Route::get('/plinko', [GameViewController::class, 'plinko']);
Route::get('/ruleta', [GameViewController::class, 'ruleta']);
Route::get('/hilo', [GameViewController::class, 'hilo']);
Route::get('/goldentree', [GameViewController::class, 'goldentree']);

Route::post('/debug/coins', [DebugController::class, 'addCoinsView']);
