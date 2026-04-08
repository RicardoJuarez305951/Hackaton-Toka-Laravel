<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameHistory;
use App\Models\GamePrize;
use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    private function getMultiplier(User $user): float
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

    private function updateStreak(User $user): void
    {
        $streak = $user->streak;

        if (! $streak) {
            $streak = UserStreak::create([
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

        $newMultiplier = min(1.5, $streak->multiplier + 0.05);
        $streak->multiplier = $newMultiplier;
        $streak->streak_count += 1;
        $streak->last_played_at = now();
        $streak->save();
    }

    private function resolvePlayer(Request $request): ?User
    {
        $userId = (int) $request->input('user_id');

        if ($userId <= 0) {
            return null;
        }

        return User::find($userId);
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
            'multiplier' => $this->getMultiplier($user),
        ]);
    }

    public function streak(int $userId)
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $streak = $user->streak;

        return response()->json([
            'success' => true,
            'multiplier' => $this->getMultiplier($user),
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

    public function playRasca(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bet' => 'required|integer|min:10|max:100',
        ]);

        $user = $this->resolvePlayer($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        if ($user->coins < $request->bet) {
            return response()->json(['success' => false, 'message' => 'Saldo insuficiente'], 400);
        }

        $game = Game::where('slug', 'rasca')->first();
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego rasca no configurado'], 500);
        }

        $multiplier = $this->getMultiplier($user);
        $balanceBefore = $user->coins;

        DB::beginTransaction();
        try {
            $user->decrement('coins', $request->bet);

            $prizes = [
                ['multiplier' => 0, 'weight' => 50],
                ['multiplier' => 0.25, 'weight' => 25],
                ['multiplier' => 0.5, 'weight' => 15],
                ['multiplier' => 1, 'weight' => 7],
                ['multiplier' => 2, 'weight' => 2],
                ['multiplier' => 5, 'weight' => 1],
            ];

            $totalWeight = array_sum(array_column($prizes, 'weight'));
            $random = mt_rand(1, $totalWeight);
            $currentWeight = 0;
            $selectedPrize = null;

            foreach ($prizes as $prize) {
                $currentWeight += $prize['weight'];
                if ($random <= $currentWeight) {
                    $selectedPrize = $prize;
                    break;
                }
            }

            $prizeMultiplier = $selectedPrize['multiplier'];
            $prizeAmount = (int) floor($request->bet * $prizeMultiplier * $multiplier);

            $prizeAmount = max($prizeAmount, floor($request->bet * 0.25));

            $user->increment('coins', $prizeAmount);

            $gamePrize = GamePrize::where('game_id', $game->id)
                ->where('multiplier', $prizeMultiplier)
                ->first();

            GameHistory::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_prize_id' => $gamePrize->id ?? null,
                'bet' => $request->bet,
                'prize' => $prizeAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->fresh()->coins,
                'played_at' => now(),
            ]);

            $this->updateStreak($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'prize' => $prizeAmount,
                'multiplier' => $prizeMultiplier,
                'applied_multiplier' => $multiplier,
                'balance' => $user->fresh()->coins,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Rasca play error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el juego',
            ], 500);
        }
    }

    public function playPlinko(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bet' => 'required|integer|min:10|max:100',
        ]);

        $user = $this->resolvePlayer($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        if ($user->coins < $request->bet) {
            return response()->json(['success' => false, 'message' => 'Saldo insuficiente'], 400);
        }

        $game = Game::where('slug', 'plinko')->first();
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego plinko no configurado'], 500);
        }

        $multiplier = $this->getMultiplier($user);
        $balanceBefore = $user->coins;

        DB::beginTransaction();
        try {
            $user->decrement('coins', $request->bet);

            $spline = $this->generatePlinkoSpline();
            $slotIndex = end($spline['positions']);
            $multipliers = [10, 3, 1, 0.5, 1, 3, 10];
            $prizeMultiplier = $multipliers[$slotIndex] ?? 1;

            $prizeAmount = (int) floor($request->bet * $prizeMultiplier * $multiplier);
            $prizeAmount = max($prizeAmount, floor($request->bet * 0.25));

            $user->increment('coins', $prizeAmount);

            $gamePrize = GamePrize::where('game_id', $game->id)
                ->where('multiplier', $prizeMultiplier)
                ->first();

            GameHistory::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_prize_id' => $gamePrize->id ?? null,
                'bet' => $request->bet,
                'prize' => $prizeAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->fresh()->coins,
                'played_at' => now(),
            ]);

            $this->updateStreak($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'prize' => $prizeAmount,
                'multiplier' => $prizeMultiplier,
                'applied_multiplier' => $multiplier,
                'slot_index' => $slotIndex,
                'spline' => $spline,
                'balance' => $user->fresh()->coins,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Plinko play error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el juego',
            ], 500);
        }
    }

    private function generatePlinkoSpline(): array
    {
        $positions = [];
        $currentX = 325;
        $rightDrops = 0;

        $positions[] = 0;

        for ($row = 0; $row < 6; $row++) {
            $goRight = mt_rand(0, 1) === 1;

            if ($goRight) {
                $rightDrops++;
                $currentX += 45;
            } else {
                $currentX -= 45;
            }

            $positions[] = $rightDrops;
        }

        return [
            'positions' => $positions,
            'final_slot' => $rightDrops,
        ];
    }

    public function playRuleta(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bet' => 'required|integer|min:10|max:100',
        ]);

        $user = $this->resolvePlayer($request);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        if ($user->coins < $request->bet) {
            return response()->json(['success' => false, 'message' => 'Saldo insuficiente'], 400);
        }

        $game = Game::where('slug', 'ruleta')->first();
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego ruleta no configurado'], 500);
        }

        $multiplier = $this->getMultiplier($user);
        $balanceBefore = $user->coins;

        DB::beginTransaction();
        try {
            $user->decrement('coins', $request->bet);

            $symbols = [
                ['text' => '0', 'value' => 0, 'weight' => 10],
                ['text' => '10', 'value' => 10, 'weight' => 20],
                ['text' => '20', 'value' => 20, 'weight' => 20],
                ['text' => '50', 'value' => 50, 'weight' => 15],
                ['text' => '100', 'value' => 100, 'weight' => 12],
                ['text' => '200', 'value' => 200, 'weight' => 8],
                ['text' => '500', 'value' => 500, 'weight' => 4],
                ['text' => '1000', 'value' => 1000, 'weight' => 1],
            ];

            $totalWeight = array_sum(array_column($symbols, 'weight'));
            $random = mt_rand(1, $totalWeight);
            $currentWeight = 0;
            $selectedSymbol = null;

            foreach ($symbols as $symbol) {
                $currentWeight += $symbol['weight'];
                if ($random <= $currentWeight) {
                    $selectedSymbol = $symbol;
                    break;
                }
            }

            $prizeAmount = (int) floor($selectedSymbol['value'] * $multiplier);
            $prizeAmount = max($prizeAmount, 0);

            if ($prizeAmount > 0) {
                $user->increment('coins', $prizeAmount);
            }

            $gamePrize = GamePrize::where('game_id', $game->id)
                ->where('multiplier', $selectedSymbol['value'] / $request->bet)
                ->first();

            GameHistory::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_prize_id' => $gamePrize->id ?? null,
                'bet' => $request->bet,
                'prize' => $prizeAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->fresh()->coins,
                'played_at' => now(),
            ]);

            $this->updateStreak($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'prize' => $prizeAmount,
                'multiplier' => $selectedSymbol['value'] / $request->bet,
                'applied_multiplier' => $multiplier,
                'symbol' => [
                    'text' => $selectedSymbol['text'],
                    'value' => $selectedSymbol['value'],
                ],
                'win_index' => array_search($selectedSymbol, $symbols),
                'balance' => $user->fresh()->coins,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ruleta play error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el juego',
            ], 500);
        }
    }
}
