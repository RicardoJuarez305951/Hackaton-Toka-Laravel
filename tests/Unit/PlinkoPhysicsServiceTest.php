<?php

namespace Tests\Unit;

use App\Services\PlinkoPhysicsService;
use Tests\TestCase;

class PlinkoPhysicsServiceTest extends TestCase
{
    public function test_simulation_is_deterministic_for_same_seed(): void
    {
        $service = app(PlinkoPhysicsService::class);

        $first = $service->simulate(123456);
        $second = $service->simulate(123456);

        $this->assertSame($first['slot_index'], $second['slot_index']);
        $this->assertSame($first['spline']['positions'], $second['spline']['positions']);
        $this->assertSame($first['spline']['keyframes'], $second['spline']['keyframes']);
    }

    public function test_simulation_returns_valid_keyframes_and_slot(): void
    {
        $service = app(PlinkoPhysicsService::class);
        $result = $service->simulate(20260408);

        $this->assertArrayHasKey('slot_index', $result);
        $this->assertArrayHasKey('spline', $result);
        $this->assertIsArray($result['spline']['positions']);
        $this->assertIsArray($result['spline']['keyframes']);
        $this->assertNotEmpty($result['spline']['keyframes']);

        $slotIndex = $result['slot_index'];
        $this->assertGreaterThanOrEqual(0, $slotIndex);
        $this->assertLessThanOrEqual(6, $slotIndex);

        $lastFrame = end($result['spline']['keyframes']);
        $this->assertSame('slot', $lastFrame['event']);
        $this->assertSame($slotIndex, $lastFrame['slot']);
        $this->assertSame($slotIndex, $result['spline']['final_slot']);
        $this->assertCount(7, $result['spline']['positions']);
    }
}

