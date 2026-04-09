<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HiloRound;
use App\Services\GamePlayService;
use App\Services\GameResponseFactory;
use App\Services\HiloService;
use App\Services\TransactionIdFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HiloController extends Controller
{
    public function __construct(
        private readonly GamePlayService $gamePlayService,
        private readonly HiloService $hiloService,
        private readonly GameResponseFactory $responseFactory,
        private readonly TransactionIdFactory $transactionIdFactory,
    ) {
    }

    public function start(Request $request): JsonResponse
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

        if (HiloRound::query()->where('user_id', $user->id)->where('is_active', true)->exists()) {
            return $this->errorResponse('Ya existe una ronda activa de hilo para este usuario.', 409);
        }

        if (! $this->gamePlayService->findGameBySlug('hilo')) {
            return $this->errorResponse('Juego hilo no configurado.', 500);
        }

        $balanceBefore = (int) $user->coins;

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $bet, $balanceBefore) {
                $user->decrement('coins', $bet);
                $card = $this->hiloService->drawCard();

                $round = HiloRound::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'bet' => $bet,
                        'starting_balance' => $balanceBefore,
                        'current_rank' => $card['rank'],
                        'current_suit' => $card['suit'],
                        'streak' => 0,
                        'current_multiplier' => 1.0,
                        'potential_payout' => $bet,
                        'is_active' => true,
                        'closed_at' => null,
                    ]
                );

                $balanceAfter = (int) $user->fresh()->coins;
                $visualData = array_merge([
                    'phase' => 'start',
                ], $this->hiloService->buildRoundVisualData($round));

                return $this->responseFactory->success(
                    'hilo',
                    0,
                    1.0,
                    false,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('hilo-start', $round->id),
                    [
                        'round_id' => $round->id,
                        'balance' => $balanceAfter,
                        'current_card' => $visualData['current_card'],
                        'higher_multiplier' => $visualData['higher_multiplier'],
                        'lower_multiplier' => $visualData['lower_multiplier'],
                        'can_higher' => $visualData['can_higher'],
                        'can_lower' => $visualData['can_lower'],
                        'streak' => $visualData['streak'],
                        'potential_payout' => $visualData['potential_payout'],
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Hilo start error: '.$e->getMessage());

            return $this->errorResponse('Error al iniciar la ronda de hilo.', 500);
        }
    }

    public function guess(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'direction' => 'required|string|in:higher,lower',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de juego invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        $round = HiloRound::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $round) {
            return $this->errorResponse('No hay una ronda activa de hilo.', 409);
        }

        $direction = (string) $request->input('direction');
        $stepMultiplier = $this->hiloService->calculateDirectionMultiplier($round->current_rank, $direction);
        if ($stepMultiplier <= 0) {
            return $this->errorResponse('La direccion elegida no es valida para la carta actual.', 422);
        }

        $game = $this->gamePlayService->findGameBySlug('hilo');
        if (! $game) {
            return $this->errorResponse('Juego hilo no configurado.', 500);
        }

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $round, $direction, $stepMultiplier, $game) {
                $nextCard = $this->hiloService->drawCard();
                $won = $this->hiloService->isWinningGuess($round->current_rank, $nextCard['rank'], $direction);
                $balanceCurrent = (int) $user->fresh()->coins;

                if ($won) {
                    $round->current_rank = $nextCard['rank'];
                    $round->current_suit = $nextCard['suit'];
                    $round->streak += 1;
                    $round->current_multiplier = round($round->current_multiplier * $stepMultiplier, 4);
                    $round->potential_payout = (int) floor($round->bet * $round->current_multiplier);
                    $round->save();

                    $visualData = array_merge([
                        'phase' => 'guess',
                        'direction' => $direction,
                        'step_multiplier' => $stepMultiplier,
                        'next_card' => $this->hiloService->formatCard($nextCard['rank'], $nextCard['suit']),
                    ], $this->hiloService->buildRoundVisualData($round));

                    return $this->responseFactory->success(
                        'hilo',
                        0,
                        round((float) $round->current_multiplier, 2),
                        true,
                        $visualData,
                        $balanceCurrent,
                        $balanceCurrent - $round->starting_balance,
                        $this->transactionIdFactory->make('hilo-guess', $round->id.'-'.$round->streak),
                        [
                            'round_id' => $round->id,
                            'balance' => $balanceCurrent,
                            'won' => true,
                            'next_card' => $visualData['next_card'],
                            'current_card' => $visualData['current_card'],
                            'higher_multiplier' => $visualData['higher_multiplier'],
                            'lower_multiplier' => $visualData['lower_multiplier'],
                            'can_higher' => $visualData['can_higher'],
                            'can_lower' => $visualData['can_lower'],
                            'streak' => $visualData['streak'],
                            'potential_payout' => $visualData['potential_payout'],
                            'step_multiplier' => $stepMultiplier,
                        ],
                    );
                }

                $round->is_active = false;
                $round->closed_at = now();
                $round->save();

                $history = $this->gamePlayService->createHistory([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'game_prize_id' => null,
                    'bet' => $round->bet,
                    'prize' => 0,
                    'balance_before' => $round->starting_balance,
                    'balance_after' => $balanceCurrent,
                    'meta' => [
                        'type' => 'hilo',
                        'result' => 'lose',
                        'direction' => $direction,
                        'streak' => $round->streak,
                        'current_card' => $this->hiloService->formatCard($round->current_rank, $round->current_suit),
                        'next_card' => $this->hiloService->formatCard($nextCard['rank'], $nextCard['suit']),
                    ],
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                $visualData = [
                    'phase' => 'guess',
                    'direction' => $direction,
                    'result' => 'lose',
                    'current_card' => $this->hiloService->formatCard($round->current_rank, $round->current_suit),
                    'next_card' => $this->hiloService->formatCard($nextCard['rank'], $nextCard['suit']),
                    'streak' => $round->streak,
                    'potential_payout' => 0,
                ];

                return $this->responseFactory->success(
                    'hilo',
                    0,
                    0.0,
                    false,
                    $visualData,
                    $balanceCurrent,
                    $balanceCurrent - $round->starting_balance,
                    $this->transactionIdFactory->make('hilo', $history->id),
                    [
                        'round_id' => $round->id,
                        'balance' => $balanceCurrent,
                        'won' => false,
                        'next_card' => $visualData['next_card'],
                        'current_card' => $visualData['current_card'],
                        'streak' => $round->streak,
                        'round_closed' => true,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Hilo guess error: '.$e->getMessage());

            return $this->errorResponse('Error al resolver la jugada de hilo.', 500);
        }
    }

    public function cashout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de juego invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        $round = HiloRound::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $round) {
            return $this->errorResponse('No hay una ronda activa de hilo.', 409);
        }

        if ($round->streak < 1 || $round->potential_payout < 1) {
            return $this->errorResponse('No es posible cobrar sin al menos una jugada ganada.', 422);
        }

        $game = $this->gamePlayService->findGameBySlug('hilo');
        if (! $game) {
            return $this->errorResponse('Juego hilo no configurado.', 500);
        }

        try {
            $payload = $this->gamePlayService->withTransaction(function () use ($user, $round, $game) {
                $payout = (int) $round->potential_payout;
                $balanceBeforeCredit = (int) $user->coins;
                $user->increment('coins', $payout);
                $balanceAfter = (int) $user->fresh()->coins;

                $round->is_active = false;
                $round->closed_at = now();
                $round->save();

                $history = $this->gamePlayService->createHistory([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'game_prize_id' => null,
                    'bet' => $round->bet,
                    'prize' => $payout,
                    'balance_before' => $round->starting_balance,
                    'balance_after' => $balanceAfter,
                    'meta' => [
                        'type' => 'hilo',
                        'result' => 'cashout',
                        'streak' => $round->streak,
                        'current_multiplier' => round((float) $round->current_multiplier, 2),
                        'current_card' => $this->hiloService->formatCard($round->current_rank, $round->current_suit),
                    ],
                    'played_at' => now(),
                ]);

                $this->gamePlayService->updateStreak($user);

                $visualData = [
                    'phase' => 'cashout',
                    'current_card' => $this->hiloService->formatCard($round->current_rank, $round->current_suit),
                    'streak' => $round->streak,
                    'current_multiplier' => round((float) $round->current_multiplier, 2),
                    'potential_payout' => $payout,
                ];

                return $this->responseFactory->success(
                    'hilo',
                    $payout,
                    round((float) $round->current_multiplier, 2),
                    true,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $round->starting_balance,
                    $this->transactionIdFactory->make('hilo', $history->id),
                    [
                        'round_id' => $round->id,
                        'balance' => $balanceAfter,
                        'prize' => $payout,
                        'multiplier' => round((float) $round->current_multiplier, 2),
                        'streak' => $round->streak,
                        'current_card' => $visualData['current_card'],
                        'round_closed' => true,
                        'balance_before_credit' => $balanceBeforeCredit,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Hilo cashout error: '.$e->getMessage());

            return $this->errorResponse('Error al cobrar la ronda de hilo.', 500);
        }
    }

    private function errorResponse(string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = $this->responseFactory->error($message, $status, $errors);
        unset($payload['_status']);

        return response()->json($payload, $status);
    }
}
