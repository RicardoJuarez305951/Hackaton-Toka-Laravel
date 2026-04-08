<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuletaController extends Controller
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

        $game = $this->gamePlayService->findGameBySlug('ruleta');
        if (! $game) {
            return response()->json(['success' => false, 'message' => 'Juego ruleta no configurado'], 500);
        }

        $multiplier = $this->gamePlayService->getMultiplier($user);
        $balanceBefore = $user->coins;

        try {
            $response = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $multiplier, $balanceBefore) {
                $user->decrement('coins', $bet);

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

                $gamePrize = $this->gamePlayService->findPrizeByMultiplier($game->id, $selectedSymbol['value'] / $bet);

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
                    'multiplier' => $selectedSymbol['value'] / $bet,
                    'applied_multiplier' => $multiplier,
                    'symbol' => [
                        'text' => $selectedSymbol['text'],
                        'value' => $selectedSymbol['value'],
                    ],
                    'win_index' => array_search($selectedSymbol, $symbols),
                    'balance' => $user->fresh()->coins,
                ];
            });

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Ruleta play error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el juego',
            ], 500);
        }
    }
}

