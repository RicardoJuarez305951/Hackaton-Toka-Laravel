<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class GameResponseFactory
{
    public function success(
        string $type,
        int|float $payout,
        float $multiplier,
        bool $isWin,
        array $visualData,
        int|float $newBalance,
        int|float $diff,
        ?string $transactionId,
        array $legacy = [],
    ): array {
        return array_merge([
            'success' => true,
            'system' => [
                'status' => 'success',
                'transaction_id' => $transactionId,
                'server_time' => Carbon::now('UTC')->toIso8601String(),
            ],
            'user' => [
                'new_balance' => $newBalance,
                'diff' => $diff,
            ],
            'game' => [
                'type' => $type,
                'payout' => $payout,
                'multiplier' => $multiplier,
                'is_win' => $isWin,
                'visual_data' => $visualData,
            ],
        ], $legacy);
    }

    public function error(string $message, int $statusCode = 400, array $errors = []): array
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'system' => [
                'status' => 'error',
                'transaction_id' => null,
                'server_time' => Carbon::now('UTC')->toIso8601String(),
            ],
            'user' => null,
            'game' => null,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $payload['_status'] = $statusCode;

        return $payload;
    }
}
