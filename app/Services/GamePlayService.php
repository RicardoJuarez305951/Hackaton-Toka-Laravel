<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameHistory;
use App\Models\GamePrize;
use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Support\Facades\DB;

class GamePlayService
{
    public function getMultiplier(User $user): float
    {
        $streak = $user->streak;

        if (! $streak) {
            return 1.0;
        }

        $hoursSinceLastPlay = now()->diffInHours($streak->last_played_at);

        if ($hoursSinceLastPlay >= 24) {
            $streak->multiplier = 1.0;
            $streak->streak_count = 0;
            $streak->save();

            return 1.0;
        }

        return (float) $streak->multiplier;
    }

    public function updateStreak(User $user): void
    {
        $streak = $user->streak;

        if (! $streak) {
            UserStreak::create([
                'user_id' => $user->id,
                'multiplier' => 1.05,
                'streak_count' => 1,
                'last_played_at' => now(),
            ]);

            return;
        }

        $hoursSinceLastPlay = now()->diffInHours($streak->last_played_at);

        if ($hoursSinceLastPlay >= 24) {
            $streak->multiplier = 1.05;
            $streak->streak_count = 1;
            $streak->last_played_at = now();
            $streak->save();

            return;
        }

        $streak->multiplier = min(1.5, $streak->multiplier + 0.05);
        $streak->streak_count += 1;
        $streak->last_played_at = now();
        $streak->save();
    }

    public function resolvePlayer(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        return User::find($userId);
    }

    public function findGameBySlug(string $slug): ?Game
    {
        return Game::where('slug', $slug)->first();
    }

    public function createHistory(array $data): GameHistory
    {
        return GameHistory::create($data);
    }

    public function findPrizeByMultiplier(int $gameId, float $multiplier): ?GamePrize
    {
        return GamePrize::where('game_id', $gameId)
            ->where('multiplier', $multiplier)
            ->first();
    }

    public function withTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}

