<?php

namespace Tests\Unit;

use App\Services\PlinkoPhysicsService;
use Tests\TestCase;

class PlinkoPhysicsServiceTest extends TestCase
{
    public function test_simulate_builds_deterministic_visuals_from_path(): void
    {
        $service = app(PlinkoPhysicsService::class);
        $result = $service->simulate([1, 0, 1, 1, 0, 1]);

        $this->assertSame([1, 0, 1, 1, 0, 1], $result['path']);
        $this->assertSame(4, $result['slot_index']);
        $this->assertSame(4, $result['spline']['final_slot']);
        $this->assertCount(6, $result['spline']['path']);
        $this->assertCount(7, $result['spline']['positions']);
        $this->assertNotEmpty($result['spline']['keyframes']);

        $topHit = collect($result['spline']['keyframes'])->firstWhere('event', 'hit');
        $this->assertSame(0, $topHit['row']);
        $this->assertSame(0, $topHit['col']);
    }
}
