<?php

namespace Tests\Unit;

use App\Services\RuletaSimulationService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class RuletaSimulationServiceTest extends TestCase
{
    public function test_simulate_builds_deterministic_result_from_win_index(): void
    {
        $service = new RuletaSimulationService(new class extends SecureRandomService {
            public function int(int $min, int $max): int
            {
                throw new \RuntimeException('Random should not be used in this test.');
            }
        });

        $result = $service->simulate(4);

        $this->assertSame(4, $result['win_index']);
        $this->assertSame(10, $result['raw_payout']);
        $this->assertSame(1.0, $result['multiplier']);
        $this->assertSame('#dd6860', $result['result_color']);
    }

    public function test_simulate_rejects_invalid_win_index(): void
    {
        $service = new RuletaSimulationService(new class extends SecureRandomService {
            public function int(int $min, int $max): int
            {
                return $min;
            }
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ruleta win index is out of range.');

        $service->simulate(99);
    }
}
