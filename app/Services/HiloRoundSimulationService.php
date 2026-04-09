<?php

namespace App\Services;

class HiloRoundSimulationService
{
    public const POLICIES = [
        'first_win' => 1,
        'two_wins' => 2,
        'three_wins' => 3,
    ];

    public const MIXED_POLICY_WEIGHTS = [
        'first_win' => 0.6,
        'two_wins' => 0.3,
        'three_wins' => 0.1,
    ];

    public function __construct(private readonly HiloService $hiloService)
    {
    }

    public function simulateRound(int $bet = 10, string $policy = 'first_win', ?array $cards = null, ?float $houseEdgeFactor = null): array
    {
        $this->assertValidInputs($bet, $policy, $cards);

        $houseEdgeFactor ??= HiloService::HOUSE_EDGE_FACTOR;
        $targetWins = self::POLICIES[$policy];
        $cardQueue = $cards ?? [];
        $currentCard = $this->nextCard($cardQueue);
        $startCard = $currentCard;
        $currentMultiplier = 1.0;
        $streak = 0;
        $steps = [];

        while (true) {
            $direction = $this->chooseDirection($currentCard['rank']);
            $stepMultiplier = $this->hiloService->calculateDirectionMultiplierForHouseEdge($currentCard['rank'], $direction, $houseEdgeFactor);
            $nextCard = $this->nextCard($cardQueue);
            $won = $this->hiloService->isWinningGuess($currentCard['rank'], $nextCard['rank'], $direction);

            $steps[] = [
                'direction' => $direction,
                'step_multiplier' => $stepMultiplier,
                'current_card' => $this->hiloService->formatCard($currentCard['rank'], $currentCard['suit']),
                'next_card' => $this->hiloService->formatCard($nextCard['rank'], $nextCard['suit']),
                'won' => $won,
            ];

            if (! $won) {
                return [
                    'policy' => $policy,
                    'target_wins' => $targetWins,
                    'house_edge_factor' => $houseEdgeFactor,
                    'bet' => $bet,
                    'start_card' => $this->hiloService->formatCard($startCard['rank'], $startCard['suit']),
                    'steps' => $steps,
                    'streak' => $streak,
                    'multiplier' => 0.0,
                    'payout' => 0,
                    'result' => 'lose',
                ];
            }

            $streak++;
            $currentMultiplier = round($currentMultiplier * $stepMultiplier, 4);
            $payout = (int) floor($bet * $currentMultiplier);

            if ($streak >= $targetWins) {
                return [
                    'policy' => $policy,
                    'target_wins' => $targetWins,
                    'house_edge_factor' => $houseEdgeFactor,
                    'bet' => $bet,
                    'start_card' => $this->hiloService->formatCard($startCard['rank'], $startCard['suit']),
                    'steps' => $steps,
                    'streak' => $streak,
                    'multiplier' => round($currentMultiplier, 2),
                    'payout' => $payout,
                    'result' => 'cashout',
                ];
            }

            $currentCard = $nextCard;
        }
    }

    private function chooseDirection(int $rank): string
    {
        $higherWins = $this->hiloService->countWinningCards($rank, 'higher');
        $lowerWins = $this->hiloService->countWinningCards($rank, 'lower');

        if ($higherWins <= 0) {
            return 'lower';
        }

        if ($lowerWins <= 0) {
            return 'higher';
        }

        return $lowerWins > $higherWins ? 'lower' : 'higher';
    }

    private function nextCard(array &$cardQueue): array
    {
        if ($cardQueue !== []) {
            $card = array_shift($cardQueue);
            $this->assertValidCard($card);

            return [
                'rank' => (int) $card['rank'],
                'suit' => (string) $card['suit'],
            ];
        }

        return $this->hiloService->drawCard();
    }

    private function assertValidInputs(int $bet, string $policy, ?array $cards): void
    {
        if ($bet < 1) {
            throw new \InvalidArgumentException('BET must be at least 1.');
        }

        if (! array_key_exists($policy, self::POLICIES)) {
            throw new \InvalidArgumentException('Unsupported Hilo policy.');
        }

        if ($cards !== null) {
            if (count($cards) < 2) {
                throw new \InvalidArgumentException('Hilo card sequences must contain at least two cards.');
            }

            foreach ($cards as $card) {
                $this->assertValidCard($card);
            }
        }
    }

    private function assertValidCard(mixed $card): void
    {
        if (! is_array($card)) {
            throw new \InvalidArgumentException('Hilo cards must be arrays.');
        }

        $rank = $card['rank'] ?? null;
        $suit = $card['suit'] ?? null;

        if (! is_int($rank) || $rank < 2 || $rank > 14) {
            throw new \InvalidArgumentException('Hilo card rank must be an integer between 2 and 14.');
        }

        if (! is_string($suit) || ! array_key_exists($suit, HiloService::SUITS)) {
            throw new \InvalidArgumentException('Hilo card suit is invalid.');
        }
    }
}
