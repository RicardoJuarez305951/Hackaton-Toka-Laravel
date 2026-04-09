<?php

namespace App\Http\Controllers;

use App\Models\Game;

class GameViewController extends Controller
{
    private const DEFAULT_WEB_BALANCE = 1000;

    private function renderMiniAppWeb(string $gameSlug)
    {
        $indexPath = public_path('miniapp-web/index.html');

        abort_unless($indexPath && file_exists($indexPath), 503, 'MiniApp web build not found. Run npm run miniapp:build.');

        return view('games.miniapp-host', [
            'gameSlug' => $gameSlug,
            'miniAppUrl' => "/miniapp-web/index.html#/{$gameSlug}",
        ]);
    }

    public function index()
    {
        $games = Game::where('is_active', true)->get();
        $user = (object) [
            'coins' => (int) session('web_demo_balance', self::DEFAULT_WEB_BALANCE),
        ];

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
