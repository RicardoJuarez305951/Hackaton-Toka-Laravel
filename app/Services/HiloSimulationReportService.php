<?php

namespace App\Services;

class HiloSimulationReportService
{
    public const DEFAULT_TOTAL_ROUNDS = 1000;
    public const DEFAULT_TOTAL_BET = 10;
    public const TARGET_MAX_RTP = 92.0;

    public function __construct(private readonly HiloRoundSimulationService $hiloRoundSimulationService)
    {
    }

    public function simulate(int $totalRounds = self::DEFAULT_TOTAL_ROUNDS, int $totalBet = self::DEFAULT_TOTAL_BET, ?array $cardsByPolicy = null, ?float $houseEdgeFactor = null): array
    {
        return $this->simulateInternal($totalRounds, $totalBet, $cardsByPolicy, $houseEdgeFactor ?? HiloService::HOUSE_EDGE_FACTOR, true);
    }

    private function simulateInternal(int $totalRounds, int $totalBet, ?array $cardsByPolicy, float $houseEdgeFactor, bool $includeRecommendation): array
    {
        $this->assertValidInputs($totalRounds, $totalBet, $cardsByPolicy);
        $policySummaries = [];

        foreach (array_keys(HiloRoundSimulationService::POLICIES) as $policy) {
            $rounds = [];

            for ($round = 0; $round < $totalRounds; $round++) {
                $rounds[] = $this->hiloRoundSimulationService->simulateRound(
                    $totalBet,
                    $policy,
                    $cardsByPolicy[$policy][$round] ?? null,
                    $houseEdgeFactor
                );
            }

            $policySummaries[$policy] = $this->summarizePolicy($policy, $rounds, $totalBet);
        }

        $mixedSummary = $this->buildMixedSummary($policySummaries, $totalRounds, $totalBet);
        $worstCaseSummary = collect($policySummaries)->sortByDesc('rtp')->first();

        return [
            'policies' => array_values($policySummaries),
            'mixed_summary' => $mixedSummary,
            'worst_case_summary' => $worstCaseSummary,
            'house_edge_factor' => $houseEdgeFactor,
            'recommended_house_edge_factor' => $includeRecommendation
                ? $this->recommendedHouseEdgeFactor($mixedSummary['rtp'], $totalRounds, $totalBet, $cardsByPolicy, $houseEdgeFactor)
                : $houseEdgeFactor,
        ];
    }

    private function summarizePolicy(string $policy, array $rounds, int $bet): array
    {
        $totalPrize = array_sum(array_map(static fn (array $round): int => (int) $round['payout'], $rounds));
        $totalRounds = count($rounds);
        $totalWagered = $totalRounds * $bet;
        $winRounds = count(array_filter($rounds, static fn (array $round): bool => $round['result'] === 'cashout'));
        $lossRounds = $totalRounds - $winRounds;

        return [
            'policy' => $policy,
            'target_wins' => HiloRoundSimulationService::POLICIES[$policy],
            'total_rounds' => $totalRounds,
            'total_bet' => $bet,
            'total_wagered' => $totalWagered,
            'total_prize' => $totalPrize,
            'net_result' => $totalPrize - $totalWagered,
            'rtp' => round(($totalPrize / max(1, $totalWagered)) * 100, 2),
            'win_rounds' => $winRounds,
            'loss_rounds' => $lossRounds,
            'avg_payout' => round($totalPrize / max(1, $totalRounds), 2),
        ];
    }

    private function buildMixedSummary(array $policySummaries, int $totalRounds, int $bet): array
    {
        $totalWagered = $totalRounds * $bet;
        $totalPrize = 0.0;

        foreach (HiloRoundSimulationService::MIXED_POLICY_WEIGHTS as $policy => $weight) {
            $totalPrize += ($policySummaries[$policy]['total_prize'] ?? 0) * $weight;
        }

        return [
            'policy' => 'mixed',
            'weights' => HiloRoundSimulationService::MIXED_POLICY_WEIGHTS,
            'total_rounds' => $totalRounds,
            'total_bet' => $bet,
            'total_wagered' => $totalWagered,
            'total_prize' => round($totalPrize, 2),
            'net_result' => round($totalPrize - $totalWagered, 2),
            'rtp' => round(($totalPrize / max(1, $totalWagered)) * 100, 2),
        ];
    }

    private function recommendedHouseEdgeFactor(float $mixedRtp, int $totalRounds, int $totalBet, ?array $cardsByPolicy, float $currentHouseEdgeFactor): float
    {
        if ($mixedRtp <= self::TARGET_MAX_RTP) {
            return $currentHouseEdgeFactor;
        }

        for ($factor = $currentHouseEdgeFactor - 0.01; $factor >= 0.10; $factor = round($factor - 0.01, 2)) {
            $report = $this->simulateInternal($totalRounds, $totalBet, $cardsByPolicy, $factor, false);

            if ($report['mixed_summary']['rtp'] <= self::TARGET_MAX_RTP) {
                return $factor;
            }
        }

        return 0.10;
    }

    private function assertValidInputs(int $totalRounds, int $totalBet, ?array $cardsByPolicy): void
    {
        if ($totalRounds < 1) {
            throw new \InvalidArgumentException('TOTAL_ROUNDS must be at least 1.');
        }

        if ($totalBet < 1) {
            throw new \InvalidArgumentException('TOTAL_BET must be at least 1.');
        }

        if ($cardsByPolicy === null) {
            return;
        }

        foreach ($cardsByPolicy as $policy => $rounds) {
            if (! array_key_exists($policy, HiloRoundSimulationService::POLICIES)) {
                throw new \InvalidArgumentException('Unsupported Hilo policy override.');
            }

            if (count($rounds) !== $totalRounds) {
                throw new \InvalidArgumentException('When Hilo card overrides are provided, each policy must define TOTAL_ROUNDS rounds.');
            }
        }
    }
}
