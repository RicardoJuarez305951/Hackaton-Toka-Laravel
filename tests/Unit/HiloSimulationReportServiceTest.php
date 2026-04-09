<?php

namespace Tests\Unit;

use App\Services\HiloRoundSimulationService;
use App\Services\HiloService;
use App\Services\HiloSimulationReportService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class HiloSimulationReportServiceTest extends TestCase
{
    public function test_simulate_builds_policy_mixed_and_worst_case_summaries(): void
    {
        $service = new HiloSimulationReportService(
            new HiloRoundSimulationService(
                new HiloService(new class extends SecureRandomService {
                    public function int(int $min, int $max): int
                    {
                        throw new \RuntimeException('Random should not be used in this test.');
                    }
                })
            )
        );

        $report = $service->simulate(2, 10, [
            'first_win' => [
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 8, 'suit' => 'hearts'],
                ],
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 12, 'suit' => 'hearts'],
                ],
            ],
            'two_wins' => [
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 8, 'suit' => 'hearts'],
                    ['rank' => 10, 'suit' => 'diamonds'],
                ],
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 12, 'suit' => 'hearts'],
                ],
            ],
            'three_wins' => [
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 8, 'suit' => 'hearts'],
                    ['rank' => 10, 'suit' => 'diamonds'],
                    ['rank' => 7, 'suit' => 'clubs'],
                ],
                [
                    ['rank' => 10, 'suit' => 'spades'],
                    ['rank' => 8, 'suit' => 'hearts'],
                    ['rank' => 7, 'suit' => 'clubs'],
                ],
            ],
        ]);

        $policies = collect($report['policies'])->keyBy('policy');

        $this->assertSame(13, $policies['first_win']['total_prize']);
        $this->assertSame(65.0, $policies['first_win']['rtp']);
        $this->assertSame(24, $policies['two_wins']['total_prize']);
        $this->assertSame(120.0, $policies['two_wins']['rtp']);
        $this->assertSame(32, $policies['three_wins']['total_prize']);
        $this->assertSame(160.0, $policies['three_wins']['rtp']);

        $this->assertSame(18.2, $report['mixed_summary']['total_prize']);
        $this->assertSame(91.0, $report['mixed_summary']['rtp']);
        $this->assertSame('three_wins', $report['worst_case_summary']['policy']);
    }

    public function test_simulate_rejects_invalid_round_count(): void
    {
        $service = new HiloSimulationReportService(
            new HiloRoundSimulationService(
                new HiloService(new class extends SecureRandomService {
                    public function int(int $min, int $max): int
                    {
                        return $min;
                    }
                })
            )
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TOTAL_ROUNDS must be at least 1.');

        $service->simulate(0, 10);
    }
}
