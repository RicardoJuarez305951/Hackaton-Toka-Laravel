<?php

namespace Tests\Unit;

use App\Services\RascaSimulationReportService;
use App\Services\RascaSimulationService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class RascaSimulationReportServiceTest extends TestCase
{
    public function test_simulate_aggregates_level_totals_and_summary(): void
    {
        $service = new RascaSimulationReportService(
            new RascaSimulationService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    throw new \RuntimeException('Random should not be used in this test.');
                }
            })
        );

        $report = $service->simulate(5, 10, [
            [1, 67, 1, 1, 1],
            [1, 1, 17, 1, 1],
            [1, 1, 1, 5, 1],
            [1, 1, 1, 1, 2],
            [1, 1, 1, 1, 1],
        ]);

        $this->assertSame([
            ['final_level' => 0, 'name' => 'none', 'raw_payout' => 0, 'multiplier' => 0.0, 'plays' => 0, 'bet_total' => 0, 'prize_total' => 0],
            ['final_level' => 1, 'name' => 'common', 'raw_payout' => 5, 'multiplier' => 0.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 5],
            ['final_level' => 2, 'name' => 'uncommon', 'raw_payout' => 10, 'multiplier' => 1.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 10],
            ['final_level' => 3, 'name' => 'rare', 'raw_payout' => 20, 'multiplier' => 2.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 20],
            ['final_level' => 4, 'name' => 'epic', 'raw_payout' => 50, 'multiplier' => 5.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 50],
            ['final_level' => 5, 'name' => 'legendary', 'raw_payout' => 200, 'multiplier' => 20.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 200],
        ], $report['levels']);

        $this->assertSame([
            'total_plays' => 5,
            'total_bet' => 10,
            'total_wagered' => 50,
            'total_prize' => 285,
            'net_result' => 235,
        ], $report['summary']);
    }

    public function test_simulate_rejects_invalid_total_plays(): void
    {
        $service = new RascaSimulationReportService(
            new RascaSimulationService(new class extends SecureRandomService {
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
        $service = new RascaSimulationReportService(
            new RascaSimulationService(new class extends SecureRandomService {
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

    public function test_simulate_rejects_invalid_roll_set_count(): void
    {
        $service = new RascaSimulationReportService(
            new RascaSimulationService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    return $min;
                }
            })
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('When roll sets are provided, their count must match TOTAL_PLAYS.');

        $service->simulate(2, 10, [[1, 1, 1, 1, 1]]);
    }
}
