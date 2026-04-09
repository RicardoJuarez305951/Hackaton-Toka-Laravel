<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class GameViewController extends Controller
{
    private function renderMiniAppWeb(string $gameSlug)
    {
        $indexPath = public_path('miniapp-web/index.html');

        abort_unless($indexPath && file_exists($indexPath), 503, 'MiniApp web build not found. Run npm run miniapp:build.');

        return view('games.miniapp-host', [
            'gameSlug' => $gameSlug,
            'miniAppUrl' => "/miniapp-web/index.html#/{$gameSlug}",
        ]);
    }

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
        return $this->renderMiniAppWeb('rasca');
    }

    public function plinko()
    {
        return $this->renderMiniAppWeb('plinko');
    }

    public function ruleta()
    {
        return $this->renderMiniAppWeb('ruleta');
    }

    public function hilo()
    {
        return $this->renderMiniAppWeb('hilo');
    }

    public function goldentree()
    {
        return $this->renderMiniAppWeb('goldentree');
    }
}
