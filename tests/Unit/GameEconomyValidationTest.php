<?php

namespace Tests\Unit;

use App\Services\PlinkoSimulationReportService;
use App\Services\RascaSimulationService;
use App\Services\RuletaSimulationReportService;
use Tests\TestCase;

class GameEconomyValidationTest extends TestCase
{
    public function test_plinko_rtp_stays_within_target_band(): void
    {
        $paths = [];

        for ($mask = 0; $mask < 64; $mask++) {
            $path = [];

            for ($bit = 5; $bit >= 0; $bit--) {
                $path[] = ($mask >> $bit) & 1;
            }

            $paths[] = $path;
        }

        $report = app(PlinkoSimulationReportService::class)->simulate(64, 10, $paths);
        $rtp = round(($report['summary']['total_prize'] / $report['summary']['total_wagered']) * 100, 2);

        $this->assertGreaterThanOrEqual(94.5, $rtp);
        $this->assertLessThanOrEqual(95.5, $rtp);
    }

    public function test_rasca_rtp_stays_within_target_band(): void
    {
        $levels = RascaSimulationService::LEVELS;
        $probabilityReachLevel = 1.0;
        $expectedMultiplier = 0.0;

        foreach ($levels as $index => $level) {
            $passProbability = $level['probability'] / 100;
            $isFinalLevel = $index === array_key_last($levels);
            $finalProbability = $isFinalLevel
                ? $probabilityReachLevel * $passProbability
                : $probabilityReachLevel * $passProbability * (1 - (($levels[$index + 1]['probability']) / 100));

            $expectedMultiplier += $finalProbability * ($level['raw_payout'] / 10);
            $probabilityReachLevel *= $passProbability;
        }

        $rtp = round($expectedMultiplier * 100, 2);

        $this->assertGreaterThanOrEqual(94.5, $rtp);
        $this->assertLessThanOrEqual(95.5, $rtp);
    }

    public function test_ruleta_rtp_stays_within_target_band(): void
    {
        $report = app(RuletaSimulationReportService::class)->simulate(8000, 10, array_merge(...array_fill(0, 1000, [0, 1, 2, 3, 4, 5, 6, 7])));

        $this->assertGreaterThanOrEqual(94.5, $report['summary']['rtp']);
        $this->assertLessThanOrEqual(95.5, $report['summary']['rtp']);
    }
}
