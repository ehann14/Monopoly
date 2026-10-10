<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/menu', [GameController::class, 'menu'])->name('menu');
    Route::post('/game/local', [GameController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('game.store');
    Route::post('/game/ai', [GameController::class, 'storeAi'])
        ->middleware('throttle:10,1')
        ->name('game.ai.store');

    Route::get('/game/{room:code}', [GameController::class, 'show'])->name('game.show');
    Route::get('/game/{room:code}/state', [GameController::class, 'state'])
        ->middleware('throttle:120,1')
        ->name('game.state');
    Route::post('/game/{room:code}/action/{action}', [GameController::class, 'action'])
        ->whereIn('action', [
            'roll', 'buy', 'decline', 'end_turn',
            'build', 'sell_building', 'mortgage', 'unmortgage',
            'pay_jail', 'use_jail_card', 'pay_debt', 'declare_bankruptcy',
        ])
        ->middleware('throttle:60,1')
        ->name('game.action');
    Route::post('/game/{room:code}/ai-step', [GameController::class, 'aiStep'])
        ->middleware('throttle:120,1')
        ->name('game.ai.step');
});