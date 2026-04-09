<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use App\Services\GameResponseFactory;
use App\Services\PlinkoPhysicsService;
use App\Services\TransactionIdFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlinkoController extends Controller
{
    private const MULTIPLIERS = [5, 2, 1, 0.5, 1, 2, 5];

    public function __construct(
        private readonly GamePlayService $gamePlayService,
        private readonly PlinkoPhysicsService $plinkoPhysicsService,
        private readonly GameResponseFactory $responseFactory,
        private readonly TransactionIdFactory $transactionIdFactory,
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

        $game = $this->gamePlayService->findGameBySlug('plinko');
        if (! $game) {
            return $this->errorResponse('Juego plinko no configurado.', 500);
        }

        $balanceBefore = (int) $user->coins;

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $balanceBefore) {
                $user->decrement('coins', $bet);

                $simulation = $this->plinkoPhysicsService->simulate();
                $path = $simulation['path'];
                $slotIndex = (int) $simulation['slot_index'];
                $multiplier = (float) (self::MULTIPLIERS[$slotIndex] ?? 1);
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
                        'type' => 'plinko',
                        'path' => $path,
                        'slot_index' => $slotIndex,
                        'spline' => $simulation['spline'],
                    ],
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                $visualData = [
                    'path' => $path,
                    'final_slot' => $slotIndex,
                    'spline' => $simulation['spline'],
                ];

                return $this->responseFactory->success(
                    'plinko',
                    $payout,
                    $multiplier,
                    $payout > 0,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('plinko', $history->id),
                    [
                        'prize' => $payout,
                        'multiplier' => $multiplier,
                        'applied_multiplier' => 1.0,
                        'slot_index' => $slotIndex,
                        'balance' => $balanceAfter,
                        'spline' => $simulation['spline'],
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Plinko play error: '.$e->getMessage());

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
