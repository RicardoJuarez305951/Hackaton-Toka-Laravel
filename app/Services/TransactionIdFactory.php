<?php

namespace App\Services;

class TransactionIdFactory
{
    public function make(string $gameCode, int|string $reference): string
    {
        $normalizedGameCode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $gameCode) ?: 'GAME');
        $normalizedReference = strtoupper((string) $reference);

        return sprintf('TX-%s-%s', $normalizedGameCode, $normalizedReference);
    }
}
