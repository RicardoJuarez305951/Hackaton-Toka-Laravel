<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GameStreakApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_streak_endpoint_returns_daily_utc_state(): void
    {
        $user = User::create([
            'name' => 'Streak Player',
            'email' => 'streak-api@example.com',
            'password' => 'password123',
            'coins' => 1000,
        ]);

        UserStreak::create([
            'user_id' => $user->id,
            'multiplier' => 1.35,
            'streak_count' => 4,
            'last_played_at' => Carbon::create(2026, 4, 7, 10, 0, 0, 'UTC'),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, 'UTC'));

        $response = $this->getJson("/api/user/{$user->id}/streak");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('multiplier', 1)
            ->assertJsonPath('streak_count', 0);
    }
}
