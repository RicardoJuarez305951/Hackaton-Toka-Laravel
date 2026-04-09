<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use App\Services\GameResponseFactory;
use App\Services\SecureRandomService;
use App\Services\TransactionIdFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RascaController extends Controller
{
    private const LEVELS = [
        ['probability' => 100, 'raw_payout' => 2, 'name' => 'common'],
        ['probability' => 70, 'raw_payout' => 10, 'name' => 'uncommon'],
        ['probability' => 40, 'raw_payout' => 25, 'name' => 'rare'],
        ['probability' => 20, 'raw_payout' => 60, 'name' => 'epic'],
        ['probability' => 10, 'raw_payout' => 200, 'name' => 'legendary'],
    ];

    public function __construct(
        private readonly GamePlayService $gamePlayService,
        private readonly GameResponseFactory $responseFactory,
        private readonly TransactionIdFactory $transactionIdFactory,
        private readonly SecureRandomService $random,
    ) {
    }

    public function play(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'bet' => 'required|integer|min:10|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de juego invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        $bet = (int) $request->input('bet');
        if (! $user->canPlay($bet)) {
            return $this->errorResponse('Saldo insuficiente.', 400);
        }

        $game = $this->gamePlayService->findGameBySlug('rasca');
        if (! $game) {
            return $this->errorResponse('Juego rasca no configurado.', 500);
        }

        $balanceBefore = (int) $user->coins;

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $balanceBefore) {
                $user->decrement('coins', $bet);

                $steps = [];
                $finalLevel = 0;
                $rawPayout = 0;

                foreach (self::LEVELS as $index => $level) {
                    $roll = $this->random->int(1, 100);
                    $passed = $roll <= $level['probability'];

                    $steps[] = [
                        'level' => $index + 1,
                        'passed' => $passed,
                        'raw_payout' => $level['raw_payout'],
                        'probability' => $level['probability'],
                        'roll' => $roll,
                        'name' => $level['name'],
                    ];

                    if (! $passed) {
                        break;
                    }

                    $finalLevel = $index + 1;
                    $rawPayout = $level['raw_payout'];
                }

                $multiplier = $rawPayout / 10;
                $payout = (int) floor($bet * $multiplier);

                if ($payout > 0) {
                    $user->increment('coins', $payout);
                }

                $balanceAfter = (int) $user->fresh()->coins;
                $gamePrize = $this->gamePlayService->findPrizeByMultiplier($game->id, $multiplier);
                $history = $this->gamePlayService->createHistory([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'game_prize_id' => $gamePrize?->id,
                    'bet' => $bet,
                    'prize' => $payout,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'meta' => [
                        'type' => 'rasca',
                        'steps' => $steps,
                        'final_level' => $finalLevel,
                        'raw_payout' => $rawPayout,
                    ],
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                $visualData = [
                    'steps' => $steps,
                    'final_level' => $finalLevel,
                    'raw_payout' => $rawPayout,
                    'prize_map' => array_map(fn (array $level) => $level['raw_payout'], self::LEVELS),
                ];

                return $this->responseFactory->success(
                    'rasca',
                    $payout,
                    $multiplier,
                    $payout > 0,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('rasca', $history->id),
                    [
                        'prize' => $payout,
                        'multiplier' => $multiplier,
                        'applied_multiplier' => 1.0,
                        'balance' => $balanceAfter,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Rasca play error: '.$e->getMessage());

            return $this->errorResponse('Error al procesar el juego.', 500);
        }
    }

    private function errorResponse(string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = $this->responseFactory->error($message, $status, $errors);
        unset($payload['_status']);

        return response()->json($payload, $status);
    }
}
