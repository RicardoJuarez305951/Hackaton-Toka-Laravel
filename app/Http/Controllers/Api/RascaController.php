<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RascaController extends Controller
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

        $game = $this->gamePlayService->findGameBySlug('rasca');
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego rasca no configurado'], 500);
        }

        $multiplier = $this->gamePlayService->getMultiplier($user);
        $balanceBefore = $user->coins;

        try {
            $response = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $multiplier, $balanceBefore) {
                $user->decrement('coins', $bet);

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

                $prizeMultiplier = (float) $selectedPrize['multiplier'];
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
                    'balance' => $user->fresh()->coins,
                ];
            });

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Rasca play error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el juego',
            ], 500);
        }
    }
}

