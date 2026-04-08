<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;

class GameViewController extends Controller
{
    public function index()
    {
        $user = User::find(1);
        $games = Game::where('is_active', true)->get();

        return view('games.index', [
            'user' => $user,
            'games' => $games,
        ]);
    }

    public function rasca()
    {
        $user = User::find(1);

        return view('games.rasca', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }

    public function plinko()
    {
        $user = User::find(1);

        return view('games.plinko', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }

    public function ruleta()
    {
        $user = User::find(1);

        return view('games.ruleta', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }
}
