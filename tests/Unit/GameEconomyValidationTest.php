<?php

namespace Tests\Unit;

use App\Services\GoldenTreeEconomicSimulationService;
use App\Services\GoldenTreeSimulationReportService;
use App\Services\HiloRoundSimulationService;
use App\Services\HiloService;
use App\Services\HiloSimulationReportService;
use App\Services\RuletaSimulationReportService;
use App\Services\RuletaSimulationService;
use Tests\TestCase;

class GameEconomyValidationTest extends TestCase
{
    public function test_ruleta_rtp_stays_within_target_band(): void
    {
        $report = app(RuletaSimulationReportService::class)->simulate(8000, 10, array_merge(...array_fill(0, 1000, [0, 1, 2, 3, 4, 5, 6, 7])));

        $this->assertGreaterThanOrEqual(88.0, $report['summary']['rtp']);
        $this->assertLessThanOrEqual(92.0, $report['summary']['rtp']);
    }

    public function test_hilo_mixed_rtp_stays_below_cap(): void
    {
        $report = app(HiloSimulationReportService::class)->simulate(3000, 10);

        $this->assertLessThanOrEqual(92.0, $report['mixed_summary']['rtp']);
    }

    public function test_goldentree_report_flags_profiles_that_remain_positive_for_user(): void
    {
        $report = app(GoldenTreeSimulationReportService::class)->simulate([24, 168], 'all');

        $this->assertArrayHasKey('target_met', $report['validation']);
        $this->assertArrayHasKey('requires_rate_redesign', $report['validation']);
    }
}
