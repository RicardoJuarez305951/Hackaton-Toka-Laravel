<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use App\Services\GameResponseFactory;
use App\Services\RuletaSimulationService;
use App\Services\TransactionIdFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RuletaController extends Controller
{
    public function __construct(
        private readonly GamePlayService $gamePlayService,
        private readonly GameResponseFactory $responseFactory,
        private readonly TransactionIdFactory $transactionIdFactory,
        private readonly RuletaSimulationService $ruletaSimulationService,
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

        $game = $this->gamePlayService->findGameBySlug('ruleta');
        if (! $game) {
            return $this->errorResponse('Juego ruleta no configurado.', 500);
        }

        $balanceBefore = (int) $user->coins;

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $bet, $game, $balanceBefore) {
                $user->decrement('coins', $bet);

                $simulation = $this->ruletaSimulationService->simulate();
                $winIndex = (int) $simulation['win_index'];
                $rawPayout = (int) $simulation['raw_payout'];
                $multiplier = (float) $simulation['multiplier'];
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
                        'type' => 'ruleta',
                        'win_index' => $winIndex,
                        'raw_payout' => $rawPayout,
                        'result_color' => $simulation['result_color'],
                    ],
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                $visualData = [
                    'win_index' => $winIndex,
                    'raw_payout' => $rawPayout,
                    'result_color' => $simulation['result_color'],
                ];

                return $this->responseFactory->success(
                    'ruleta',
                    $payout,
                    $multiplier,
                    $payout > 0,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('ruleta', $history->id),
                    [
                        'prize' => $payout,
                        'multiplier' => $multiplier,
                        'applied_multiplier' => 1.0,
                        'symbol' => [
                            'text' => (string) $rawPayout,
                            'value' => $rawPayout,
                        ],
                        'win_index' => $winIndex,
                        'balance' => $balanceAfter,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Ruleta play error: '.$e->getMessage());

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
