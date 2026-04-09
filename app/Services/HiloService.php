<?php

namespace App\Services;

use App\Models\HiloRound;

class HiloService
{
    private const SUITS = [
        'spades' => ['symbol' => '?', 'color' => 'black'],
        'hearts' => ['symbol' => '?', 'color' => 'red'],
        'diamonds' => ['symbol' => '?', 'color' => 'red'],
        'clubs' => ['symbol' => '?', 'color' => 'black'],
    ];

    public function __construct(private readonly SecureRandomService $random)
    {
    }

    public function drawCard(): array
    {
        $rank = $this->random->int(2, 14);
        $suitKeys = array_keys(self::SUITS);
        $suit = $suitKeys[$this->random->pickIndex($suitKeys)];

        return [
            'rank' => $rank,
            'suit' => $suit,
        ];
    }

    public function formatCard(int $rank, string $suit): array
    {
        $config = self::SUITS[$suit] ?? self::SUITS['spades'];

        return [
            'rank' => $this->displayRank($rank),
            'value' => $rank,
            'suit' => $config['symbol'],
            'color' => $config['color'],
        ];
    }

    public function multipliersForCard(int $rank): array
    {
        $higher = $this->calculateDirectionMultiplier($rank, 'higher');
        $lower = $this->calculateDirectionMultiplier($rank, 'lower');

        return [
            'higher_multiplier' => $higher,
            'lower_multiplier' => $lower,
            'can_higher' => $higher > 0,
            'can_lower' => $lower > 0,
        ];
    }

    public function calculateDirectionMultiplier(int $rank, string $direction): float
    {
        $winningCards = $this->countWinningCards($rank, $direction);

        if ($winningCards <= 0) {
            return 0.0;
        }

        return round((12 / $winningCards) * 0.9, 2);
    }

    public function countWinningCards(int $rank, string $direction): int
    {
        return $direction === 'higher'
            ? max(0, 14 - $rank)
            : max(0, $rank - 2);
    }

    public function isWinningGuess(int $currentRank, int $nextRank, string $direction): bool
    {
        if ($nextRank === $currentRank) {
            return false;
        }

        return $direction === 'higher'
            ? $nextRank > $currentRank
            : $nextRank < $currentRank;
    }

    public function buildRoundVisualData(HiloRound $round): array
    {
        return array_merge([
            'current_card' => $this->formatCard($round->current_rank, $round->current_suit),
            'streak' => $round->streak,
            'current_multiplier' => round((float) $round->current_multiplier, 2),
            'potential_payout' => (int) $round->potential_payout,
        ], $this->multipliersForCard($round->current_rank));
    }

    private function displayRank(int $rank): string
    {
        return match ($rank) {
            11 => 'J',
            12 => 'Q',
            13 => 'K',
            14 => 'A',
            default => (string) $rank,
        };
    }
}
