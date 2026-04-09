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
            ['slot' => 0, 'multiplier' => 5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 50],
            ['slot' => 1, 'multiplier' => 2, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 20],
            ['slot' => 2, 'multiplier' => 1, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 10],
            ['slot' => 3, 'multiplier' => 0.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 5],
            ['slot' => 4, 'multiplier' => 1, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 10],
            ['slot' => 5, 'multiplier' => 2, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 20],
            ['slot' => 6, 'multiplier' => 5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 50],
        ], $report['slots']);

        $this->assertSame([
            'total_plays' => 7,
            'total_bet' => 10,
            'total_wagered' => 70,
            'total_prize' => 165,
            'net_result' => 95,
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
