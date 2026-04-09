<?php

namespace App\Services;

class RuletaSimulationReportService
{
    public const DEFAULT_TOTAL_PLAYS = 1000;
    public const DEFAULT_TOTAL_BET = 10;

    public function __construct(private readonly RuletaSimulationService $ruletaSimulationService)
    {
    }

    public function simulate(int $totalPlays = self::DEFAULT_TOTAL_PLAYS, int $totalBet = self::DEFAULT_TOTAL_BET, ?array $winIndices = null): array
    {
        $this->assertValidInputs($totalPlays, $totalBet, $winIndices);

        $segments = [];

        foreach (RuletaSimulationService::RAW_PAYOUTS as $index => $rawPayout) {
            $segments[$index] = [
                'win_index' => $index,
                'raw_payout' => $rawPayout,
                'multiplier' => (float) ($rawPayout / 10),
                'plays' => 0,
                'bet_total' => 0,
                'prize_total' => 0,
            ];
        }

        $totalPrize = 0;

        for ($play = 0; $play < $totalPlays; $play++) {
            $simulation = $this->ruletaSimulationService->simulate($winIndices[$play] ?? null);
            $winIndex = (int) $simulation['win_index'];
            $prize = (int) floor($totalBet * $simulation['multiplier']);

            $segments[$winIndex]['plays']++;
            $segments[$winIndex]['bet_total'] += $totalBet;
            $segments[$winIndex]['prize_total'] += $prize;
            $totalPrize += $prize;
        }

        $totalWagered = $totalPlays * $totalBet;

        return [
            'segments' => array_values($segments),
            'summary' => [
                'total_plays' => $totalPlays,
                'total_bet' => $totalBet,
                'total_wagered' => $totalWagered,
                'total_prize' => $totalPrize,
                'net_result' => $totalPrize - $totalWagered,
                'rtp' => round(($totalPrize / max(1, $totalWagered)) * 100, 2),
            ],
        ];
    }

    private function assertValidInputs(int $totalPlays, int $totalBet, ?array $winIndices): void
    {
        if ($totalPlays < 1) {
            throw new \InvalidArgumentException('TOTAL_PLAYS must be at least 1.');
        }

        if ($totalBet < 1) {
            throw new \InvalidArgumentException('TOTAL_BET must be at least 1.');
        }

        if ($winIndices !== null && count($winIndices) !== $totalPlays) {
            throw new \InvalidArgumentException('When win indices are provided, their count must match TOTAL_PLAYS.');
        }
    }
}
