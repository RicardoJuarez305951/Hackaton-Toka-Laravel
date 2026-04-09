<?php

namespace Tests\Unit;

use App\Services\RascaSimulationService;
use App\Services\SecureRandomService;
use PHPUnit\Framework\TestCase;

class RascaSimulationServiceTest extends TestCase
{
    public function test_simulate_builds_deterministic_steps_from_rolls(): void
    {
        $service = new RascaSimulationService(new class extends SecureRandomService {
            public function int(int $min, int $max): int
            {
                throw new \RuntimeException('Random should not be used in this test.');
            }
        });

        $result = $service->simulate([1, 50, 41, 1, 1]);

        $this->assertSame(2, $result['final_level']);
        $this->assertSame(10, $result['raw_payout']);
        $this->assertSame(1.0, $result['multiplier']);
        $this->assertSame([5, 10, 20, 50, 200], $result['prize_map']);
        $this->assertCount(3, $result['steps']);
        $this->assertTrue($result['steps'][0]['passed']);
        $this->assertTrue($result['steps'][1]['passed']);
        $this->assertFalse($result['steps'][2]['passed']);
        $this->assertSame(41, $result['steps'][2]['roll']);
    }

    public function test_simulate_rejects_invalid_roll_count(): void
    {
        $service = new RascaSimulationService(new class extends SecureRandomService {
            public function int(int $min, int $max): int
            {
                return $min;
            }
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rasca rolls must have exactly 5 values.');

        $service->simulate([1, 2, 3]);
    }

    public function test_simulate_rejects_invalid_roll_values(): void
    {
        $service = new RascaSimulationService(new class extends SecureRandomService {
            public function int(int $min, int $max): int
            {
                return $min;
            }
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rasca rolls must be integers between 1 and 100.');

        $service->simulate([1, 2, 3, 4, 101]);
    }
}
