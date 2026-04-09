<?php

namespace App\Services;

class PlinkoSimulationReportService
{
    public const DEFAULT_TOTAL_PLAYS = 1000;
    public const DEFAULT_TOTAL_BET = 10;

    public function __construct(private readonly PlinkoPhysicsService $plinkoPhysicsService)
    {
    }

    public function simulate(int $totalPlays = self::DEFAULT_TOTAL_PLAYS, int $totalBet = self::DEFAULT_TOTAL_BET, ?array $paths = null): array
    {
        $this->assertValidInputs($totalPlays, $totalBet, $paths);

        $slots = [];
        foreach (PlinkoPhysicsService::SLOT_MULTIPLIERS as $slot => $multiplier) {
            $slots[$slot] = [
                'slot' => $slot,
                'multiplier' => $multiplier,
                'plays' => 0,
                'bet_total' => 0,
                'prize_total' => 0,
            ];
        }

        $totalPrize = 0;

        for ($play = 0; $play < $totalPlays; $play++) {
            $path = $paths[$play] ?? null;
            $simulation = $this->plinkoPhysicsService->simulate($path);
            $slotIndex = (int) $simulation['slot_index'];
            $multiplier = (float) (PlinkoPhysicsService::SLOT_MULTIPLIERS[$slotIndex] ?? 0);
            $prize = (int) floor($totalBet * $multiplier);

            $slots[$slotIndex]['plays']++;
            $slots[$slotIndex]['bet_total'] += $totalBet;
            $slots[$slotIndex]['prize_total'] += $prize;
            $totalPrize += $prize;
        }

        $totalWagered = $totalPlays * $totalBet;

        return [
            'slots' => array_values($slots),
            'summary' => [
                'total_plays' => $totalPlays,
                'total_bet' => $totalBet,
                'total_wagered' => $totalWagered,
                'total_prize' => $totalPrize,
                'net_result' => $totalPrize - $totalWagered,
            ],
        ];
    }

    private function assertValidInputs(int $totalPlays, int $totalBet, ?array $paths): void
    {
        if ($totalPlays < 1) {
            throw new \InvalidArgumentException('TOTAL_PLAYS must be at least 1.');
        }

        if ($totalBet < 1) {
            throw new \InvalidArgumentException('TOTAL_BET must be at least 1.');
        }

        if ($paths !== null && count($paths) !== $totalPlays) {
            throw new \InvalidArgumentException('When paths are provided, their count must match TOTAL_PLAYS.');
        }
    }
}
