<?php

namespace Tests\Unit;

use App\Services\GoldenTreeEconomicSimulationService;
use App\Services\GoldenTreeSimulationReportService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class GoldenTreeSimulationReportServiceTest extends TestCase
{
    public function test_simulate_reports_deterministic_growth_without_events(): void
    {
        $service = new GoldenTreeSimulationReportService(
            new GoldenTreeEconomicSimulationService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    return $max;
                }
            })
        );

        $report = $service->simulate([4], 'casual', ['event_trigger_chance_percent' => 0]);
        $scenario = $report['scenarios'][0];

        $this->assertSame('casual', $scenario['profile']);
        $this->assertSame(4, $scenario['hours']);
        $this->assertSame(1140, $scenario['generated_gross']);
        $this->assertSame(0, $scenario['collected_net']);
        $this->assertSame(0, $scenario['wallet_delta']);
        $this->assertTrue($scenario['target_met']);
    }

    public function test_simulate_tracks_forced_storm_penalty(): void
    {
        $service = new GoldenTreeSimulationReportService(
            new GoldenTreeEconomicSimulationService(new class extends SecureRandomService {
                public function __construct(private array $values = [1, 2, 100])
                {
                }

                public function int(int $min, int $max): int
                {
                    if ($this->values === []) {
                        return $max;
                    }

                    return array_shift($this->values);
                }
            })
        );

        $report = $service->simulate([5], 'casual');
        $scenario = $report['scenarios'][0];

        $this->assertSame(1, $scenario['missed_events']);
        $this->assertSame(1, $scenario['penalties']['storm']['count']);
        $this->assertSame(1140, $scenario['penalties']['storm']['tp_lost']);
        $this->assertSame(472, $scenario['ending_banked_tp']);
    }
}
