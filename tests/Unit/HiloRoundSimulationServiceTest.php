<?php

namespace Tests\Unit;

use App\Services\HiloRoundSimulationService;
use App\Services\HiloService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class HiloRoundSimulationServiceTest extends TestCase
{
    public function test_simulate_round_cashes_out_after_first_win_using_policy_heuristic(): void
    {
        $service = new HiloRoundSimulationService(
            new HiloService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    throw new \RuntimeException('Random should not be used in this test.');
                }
            })
        );

        $result = $service->simulateRound(10, 'first_win', [
            ['rank' => 10, 'suit' => 'spades'],
            ['rank' => 8, 'suit' => 'hearts'],
        ]);

        $this->assertSame('cashout', $result['result']);
        $this->assertSame(1, $result['streak']);
        $this->assertSame(13, $result['payout']);
        $this->assertSame(1.35, $result['multiplier']);
        $this->assertSame('lower', $result['steps'][0]['direction']);
    }

    public function test_simulate_round_rejects_invalid_card_sequence(): void
    {
        $service = new HiloRoundSimulationService(
            new HiloService(new class extends SecureRandomService {
                public function int(int $min, int $max): int
                {
                    return $min;
                }
            })
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hilo card sequences must contain at least two cards.');

        $service->simulateRound(10, 'first_win', [['rank' => 10, 'suit' => 'spades']]);
    }
}
