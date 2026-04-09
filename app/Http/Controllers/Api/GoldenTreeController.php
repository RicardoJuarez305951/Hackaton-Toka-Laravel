<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamePlayService;
use App\Services\GameResponseFactory;
use App\Services\GoldenTreeService;
use App\Services\TransactionIdFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoldenTreeController extends Controller
{
    public function __construct(
        private readonly GamePlayService $gamePlayService,
        private readonly GoldenTreeService $goldenTreeService,
        private readonly GameResponseFactory $responseFactory,
        private readonly TransactionIdFactory $transactionIdFactory,
    ) {
    }

    public function state(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de usuario invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        try {
            $state = $this->goldenTreeService->syncState($this->goldenTreeService->getOrCreateState($user));
            $visualData = $this->goldenTreeService->buildVisualData($state);

            return response()->json($this->responseFactory->success(
                'goldentree',
                0,
                0.0,
                false,
                $visualData,
                (int) $user->coins,
                0,
                $this->transactionIdFactory->make('goldentree-state', $state->id),
                [
                    'balance' => (int) $user->coins,
                    'state' => $visualData,
                ],
            ));
        } catch (\Throwable $e) {
            Log::error('GoldenTree state error: '.$e->getMessage());

            return $this->errorResponse('Error al obtener el estado del arbol.', 500);
        }
    }

    public function collect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de usuario invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        $game = $this->gamePlayService->findGameBySlug('goldentree');
        if (! $game) {
            return $this->errorResponse('Juego goldentree no configurado.', 500);
        }

        try {
            $state = $this->goldenTreeService->syncState($this->goldenTreeService->getOrCreateState($user));
            if ($state->banked_tp < 1) {
                return $this->errorResponse('No hay TP acumulado para cobrar.', 422);
            }

            $payload = $this->gamePlayService->withTransaction(function () use ($user, $state, $game) {
                $balanceBefore = (int) $user->coins;
                $collection = $this->goldenTreeService->collect($state);

                if ($collection['net'] > 0) {
                    $user->increment('coins', $collection['net']);
                }

                $balanceAfter = (int) $user->fresh()->coins;
                $history = $this->gamePlayService->createHistory([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'game_prize_id' => null,
                    'bet' => 0,
                    'prize' => $collection['net'],
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'meta' => [
                        'type' => 'goldentree',
                        'gross' => $collection['gross'],
                        'commission' => $collection['commission'],
                        'stage' => $collection['state']->stage,
                    ],
                    'played_at' => now(),
                ]);

                $visualData = array_merge(
                    $this->goldenTreeService->buildVisualData($collection['state']),
                    [
                        'collection' => [
                            'gross' => $collection['gross'],
                            'commission' => $collection['commission'],
                            'net' => $collection['net'],
                        ],
                    ]
                );

                return $this->responseFactory->success(
                    'goldentree',
                    $collection['net'],
                    0.0,
                    $collection['net'] > 0,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('goldentree', $history->id),
                    [
                        'balance' => $balanceAfter,
                        'collected' => $collection['net'],
                        'commission' => $collection['commission'],
                        'state' => $visualData,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('GoldenTree collect error: '.$e->getMessage());

            return $this->errorResponse('Error al cobrar el arbol.', 500);
        }
    }

    public function water(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de usuario invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        try {
            $state = $this->goldenTreeService->syncState($this->goldenTreeService->getOrCreateState($user));
            if ($state->active_event_id) {
                return $this->errorResponse('No se puede regar mientras hay un evento activo.', 409);
            }

            $cost = $this->goldenTreeService->getWaterCost();
            if ((int) $user->coins < $cost) {
                return $this->errorResponse('Saldo insuficiente para regar.', 400);
            }

            $payload = $this->gamePlayService->withTransaction(function () use ($user, $state, $cost) {
                $balanceBefore = (int) $user->coins;
                $user->decrement('coins', $cost);
                $updatedState = $this->goldenTreeService->water($state);
                $balanceAfter = (int) $user->fresh()->coins;
                $visualData = array_merge(
                    $this->goldenTreeService->buildVisualData($updatedState),
                    ['water_applied' => true]
                );

                return $this->responseFactory->success(
                    'goldentree',
                    0,
                    0.0,
                    true,
                    $visualData,
                    $balanceAfter,
                    $balanceAfter - $balanceBefore,
                    $this->transactionIdFactory->make('goldentree-water', $updatedState->id),
                    [
                        'balance' => $balanceAfter,
                        'water_cost' => $cost,
                        'state' => $visualData,
                    ],
                );
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('GoldenTree water error: '.$e->getMessage());

            return $this->errorResponse('Error al regar el arbol.', 500);
        }
    }

    public function resolveEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'event_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos de evento invalidos.', 422, $validator->errors()->toArray());
        }

        $user = $this->gamePlayService->resolvePlayer((int) $request->input('user_id'));
        if (! $user) {
            return $this->errorResponse('Usuario no encontrado.', 404);
        }

        $eventId = (string) $request->input('event_id');

        try {
            $state = $this->goldenTreeService->syncState($this->goldenTreeService->getOrCreateState($user));
            if (! $state->active_event_id) {
                return $this->errorResponse('No hay un evento activo para resolver.', 409);
            }

            if ($state->active_event_id !== $eventId) {
                return $this->errorResponse('El evento indicado no coincide con el evento activo.', 422);
            }

            $updatedState = $this->goldenTreeService->resolveEvent($state);
            $visualData = array_merge(
                $this->goldenTreeService->buildVisualData($updatedState),
                ['resolved_event_id' => $eventId]
            );

            return response()->json($this->responseFactory->success(
                'goldentree',
                0,
                0.0,
                true,
                $visualData,
                (int) $user->coins,
                0,
                $this->transactionIdFactory->make('goldentree-event', $updatedState->id),
                [
                    'balance' => (int) $user->coins,
                    'resolved_event_id' => $eventId,
                    'state' => $visualData,
                ],
            ));
        } catch (\Throwable $e) {
            Log::error('GoldenTree resolve event error: '.$e->getMessage());

            return $this->errorResponse('Error al resolver el evento del arbol.', 500);
        }
    }

    private function errorResponse(string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = $this->responseFactory->error($message, $status, $errors);
        unset($payload['_status']);

        return response()->json($payload, $status);
    }
}
