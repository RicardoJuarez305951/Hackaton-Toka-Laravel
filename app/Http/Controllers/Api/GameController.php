<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GamePlayService;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly GamePlayService $gamePlayService)
    {
    }

    public function balance(int $userId)
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'coins' => $user->coins,
            'multiplier' => $this->gamePlayService->getMultiplier($user),
        ]);
    }

    public function streak(int $userId)
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $multiplier = $this->gamePlayService->getMultiplier($user);
        $streak = $user->fresh()->streak;

        return response()->json([
            'success' => true,
            'multiplier' => $multiplier,
            'streak_count' => $streak ? $streak->streak_count : 0,
            'last_played_at' => $streak ? $streak->last_played_at : null,
        ]);
    }

    public function history(int $userId)
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $history = $user->history()
            ->with(['game', 'gamePrize'])
            ->orderBy('played_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'game' => $h->game->name ?? 'Unknown',
                    'game_slug' => $h->game->slug ?? '',
                    'bet' => $h->bet,
                    'prize' => $h->prize,
                    'profit' => $h->prize - $h->bet,
                    'balance_before' => $h->balance_before,
                    'balance_after' => $h->balance_after,
                    'played_at' => $h->played_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}
