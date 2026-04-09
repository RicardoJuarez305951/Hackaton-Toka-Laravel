<?php

namespace App\Services;

class RascaSimulationReportService
{
    public const DEFAULT_TOTAL_PLAYS = 1000;
    public const DEFAULT_TOTAL_BET = 10;

    public function __construct(private readonly RascaSimulationService $rascaSimulationService)
    {
    }

    public function simulate(int $totalPlays = self::DEFAULT_TOTAL_PLAYS, int $totalBet = self::DEFAULT_TOTAL_BET, ?array $rollSets = null): array
    {
        $this->assertValidInputs($totalPlays, $totalBet, $rollSets);

        $levels = [
            0 => [
                'final_level' => 0,
                'name' => 'none',
                'raw_payout' => 0,
                'multiplier' => 0.0,
                'plays' => 0,
                'bet_total' => 0,
                'prize_total' => 0,
            ],
        ];

        foreach (RascaSimulationService::LEVELS as $index => $level) {
            $finalLevel = $index + 1;
            $levels[$finalLevel] = [
                'final_level' => $finalLevel,
                'name' => $level['name'],
                'raw_payout' => $level['raw_payout'],
                'multiplier' => (float) ($level['raw_payout'] / 10),
                'plays' => 0,
                'bet_total' => 0,
                'prize_total' => 0,
            ];
        }

        $totalPrize = 0;

        for ($play = 0; $play < $totalPlays; $play++) {
            $simulation = $this->rascaSimulationService->simulate($rollSets[$play] ?? null);
            $finalLevel = (int) $simulation['final_level'];
            $prize = (int) floor($totalBet * $simulation['multiplier']);

            $levels[$finalLevel]['plays']++;
            $levels[$finalLevel]['bet_total'] += $totalBet;
            $levels[$finalLevel]['prize_total'] += $prize;
            $totalPrize += $prize;
        }

        $totalWagered = $totalPlays * $totalBet;

        return [
            'levels' => array_values($levels),
            'summary' => [
                'total_plays' => $totalPlays,
                'total_bet' => $totalBet,
                'total_wagered' => $totalWagered,
                'total_prize' => $totalPrize,
                'net_result' => $totalPrize - $totalWagered,
            ],
        ];
    }

    private function assertValidInputs(int $totalPlays, int $totalBet, ?array $rollSets): void
    {
        if ($totalPlays < 1) {
            throw new \InvalidArgumentException('TOTAL_PLAYS must be at least 1.');
        }

        if ($totalBet < 1) {
            throw new \InvalidArgumentException('TOTAL_BET must be at least 1.');
        }

        if ($rollSets !== null && count($rollSets) !== $totalPlays) {
            throw new \InvalidArgumentException('When roll sets are provided, their count must match TOTAL_PLAYS.');
        }
    }
}
