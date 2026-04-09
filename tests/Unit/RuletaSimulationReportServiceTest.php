<?php

namespace Tests\Unit;

use App\Services\RuletaSimulationReportService;
use App\Services\RuletaSimulationService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class RuletaSimulationReportServiceTest extends TestCase
{
    public function test_simulate_aggregates_segments_and_summary(): void
    {
        $service = new RuletaSimulationReportService(
            new RuletaSimulationService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    throw new \RuntimeException('Random should not be used in this test.');
                }
            })
        );

        $report = $service->simulate(8, 10, [0, 1, 2, 3, 4, 5, 6, 7]);

        $this->assertSame([
            ['win_index' => 0, 'raw_payout' => 0, 'multiplier' => 0.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 0],
            ['win_index' => 1, 'raw_payout' => 2, 'multiplier' => 0.2, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 2],
            ['win_index' => 2, 'raw_payout' => 5, 'multiplier' => 0.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 5],
            ['win_index' => 3, 'raw_payout' => 8, 'multiplier' => 0.8, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 8],
            ['win_index' => 4, 'raw_payout' => 10, 'multiplier' => 1.0, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 10],
            ['win_index' => 5, 'raw_payout' => 12, 'multiplier' => 1.2, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 12],
            ['win_index' => 6, 'raw_payout' => 15, 'multiplier' => 1.5, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 15],
            ['win_index' => 7, 'raw_payout' => 24, 'multiplier' => 2.4, 'plays' => 1, 'bet_total' => 10, 'prize_total' => 24],
        ], $report['segments']);

        $this->assertSame([
            'total_plays' => 8,
            'total_bet' => 10,
            'total_wagered' => 80,
            'total_prize' => 76,
            'net_result' => -4,
            'rtp' => 95.0,
        ], $report['summary']);
    }

    public function test_simulate_rejects_invalid_total_plays(): void
    {
        $service = new RuletaSimulationReportService(
            new RuletaSimulationService(new class extends SecureRandomService {
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
        $service = new RuletaSimulationReportService(
            new RuletaSimulationService(new class extends SecureRandomService {
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
