<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class GameViewController extends Controller
{
    private function resolveDemoUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo@toka.local'],
            [
                'name' => 'Demo Toka',
                'password' => Hash::make('password123'),
                'coins' => 1000,
            ]
        );
    }

    public function index()
    {
        $user = $this->resolveDemoUser();
        $games = Game::where('is_active', true)->get();

        return view('games.index', [
            'user' => $user,
            'games' => $games,
        ]);
    }

    public function rasca()
    {
        $user = $this->resolveDemoUser();

        return view('games.rasca', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }

    public function plinko()
    {
        $user = $this->resolveDemoUser();

        return view('games.plinko', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }

    public function ruleta()
    {
        $user = $this->resolveDemoUser();

        return view('games.ruleta', [
            'user' => $user,
            'balance' => $user->coins,
        ]);
    }
}
