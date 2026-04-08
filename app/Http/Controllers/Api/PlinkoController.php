<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlinkoController extends Controller
{
    public function __construct(private readonly GamePlayService $gamePlayService)
    {
    }

    public function play(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bet' => 'required|integer|min:10|max:100',
        ]);

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $bet = (int) $request->input('bet');

        if ($user->coins < $bet) {
            return response()->json(['success' => false, 'message' => 'Saldo insuficiente'], 400);
        }

        $game = $this->gamePlayService->findGameBySlug('plinko');
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego plinko no configurado'], 500);
        }

        $multiplier = $this->gamePlayService->getMultiplier($user);
        $balanceBefore = $user->coins;

        try {
            $response = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $multiplier, $balanceBefore) {
                $user->decrement('coins', $bet);

                $spline = $this->generatePlinkoSpline();
                $slotIndex = (int) end($spline['positions']);
                $multipliers = [10, 3, 1, 0.5, 1, 3, 10];
                $prizeMultiplier = (float) ($multipliers[$slotIndex] ?? 1);

                $prizeAmount = (int) floor($bet * $prizeMultiplier * $multiplier);
                $prizeAmount = max($prizeAmount, (int) floor($bet * 0.25));

                $user->increment('coins', $prizeAmount);

                $gamePrize = $this->gamePlayService->findPrizeByMultiplier($game->id, $prizeMultiplier);

                $this->gamePlayService->createHistory([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'game_prize_id' => $gamePrize?->id,
                    'bet' => $bet,
                    'prize' => $prizeAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $user->fresh()->coins,
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                return [
                    'success' => true,
                    'prize' => $prizeAmount,
                    'multiplier' => $prizeMultiplier,
                    'applied_multiplier' => $multiplier,
                    'slot_index' => $slotIndex,
                    'spline' => $spline,
                    'balance' => $user->fresh()->coins,
                ];
            });

            return response()->json($response);
        } catch (\Throwable $e) {
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
        $rightDrops = 0;
        $positions[] = 0;

        for ($row = 0; $row < 6; $row++) {
            $goRight = mt_rand(0, 1) === 1;

            if ($goRight) {
                $rightDrops++;
            }

            $positions[] = $rightDrops;
        }

        return [
            'positions' => $positions,
            'final_slot' => $rightDrops,
        ];
    }
}

