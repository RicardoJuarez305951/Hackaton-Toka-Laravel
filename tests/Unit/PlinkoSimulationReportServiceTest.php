<?php

namespace Tests\Unit;

use App\Services\PlinkoPhysicsService;
use App\Services\PlinkoSimulationReportService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class PlinkoSimulationReportServiceTest extends TestCase
{
    public function test_simulate_aggregates_slot_totals_and_summary(): void
    {
        $service = new PlinkoSimulationReportService(
            new PlinkoPhysicsService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    throw new \RuntimeException('Random should not be used in this test.');
                }
            })
        );

        $report = $service->simulate(7, 10, [
            [0, 0, 0, 0, 0, 0],
            [1, 0, 0, 0, 0, 0],
            [1, 1, 0, 0, 0, 0],
            [1, 1, 1, 0, 0, 0],
            [1, 1, 1, 1, 0, 0],
            [1, 1, 1, 1, 1, 0],
            [1, 1, 1, 1, 1, 1],
        ]);

        $this->assertSame([
            ['slot' => 0, 'multiplier' => 4.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 45],
            ['slot' => 1, 'multiplier' => 1.8, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 18],
            ['slot' => 2, 'multiplier' => 0.8, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 8],
            ['slot' => 3, 'multiplier' => 0.4, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 4],
            ['slot' => 4, 'multiplier' => 0.8, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 8],
            ['slot' => 5, 'multiplier' => 1.8, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 18],
            ['slot' => 6, 'multiplier' => 4.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 45],
        ], $report['slots']);

        $this->assertSame([
            'total_plays' => 7,
            'total_bet' => 10,
            'total_wagered' => 70,
            'total_prize' => 146,
            'net_result' => 76,
        ], $report['summary']);
    }

    public function test_simulate_rejects_invalid_total_plays(): void
    {
        $service = new PlinkoSimulationReportService(
            new PlinkoPhysicsService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    return $min;
                }
            })
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TOTAL_PLAYS must be at least 1.');

        $service->simulate(0, 10);
    }

    public function test_simulate_rejects_invalid_total_bet(): void
    {
        $service = new PlinkoSimulationReportService(
            new PlinkoPhysicsService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    return $min;
                }
            })
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TOTAL_BET must be at least 1.');

        $service->simulate(10, 0);
    }
}
