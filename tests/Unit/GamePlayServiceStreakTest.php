<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserStreak;
use App\Services\GamePlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GamePlayServiceStreakTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_first_play_creates_daily_streak(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, 'UTC'));

        $user = $this->makeUser('streak-first@example.com');

        app(GamePlayService::class)->updateStreak($user);

        $streak = $user->fresh()->streak;
        $this->assertNotNull($streak);
        $this->assertSame(1, $streak->streak_count);
        $this->assertSame('1.00', $streak->multiplier);
    }

    public function test_multiple_plays_on_same_utc_day_do_not_increment_streak(): void
    {
        $user = $this->makeUser('streak-same-day@example.com');

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 8, 0, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user);

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 22, 30, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user->fresh());

        $streak = $user->fresh()->streak;
        $this->assertSame(1, $streak->streak_count);
        $this->assertSame('1.00', $streak->multiplier);
    }

    public function test_next_utc_day_increments_streak_once(): void
    {
        $user = $this->makeUser('streak-next-day@example.com');

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 23, 55, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user);

        Carbon::setTestNow(Carbon::create(2026, 4, 10, 0, 5, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user->fresh());

        $streak = $user->fresh()->streak;
        $this->assertSame(2, $streak->streak_count);
        $this->assertSame('1.00', $streak->multiplier);
    }

    public function test_missing_more_than_one_utc_day_resets_streak_to_one(): void
    {
        $user = $this->makeUser('streak-reset@example.com');

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user);

        Carbon::setTestNow(Carbon::create(2026, 4, 11, 12, 0, 0, 'UTC'));
        app(GamePlayService::class)->updateStreak($user->fresh());

        $streak = $user->fresh()->streak;
        $this->assertSame(1, $streak->streak_count);
        $this->assertSame('1.00', $streak->multiplier);
    }

    public function test_get_multiplier_resets_stale_streak_and_returns_one(): void
    {
        $user = $this->makeUser('streak-multiplier@example.com');
        UserStreak::create([
            'user_id' => $user->id,
            'multiplier' => 1.25,
            'streak_count' => 4,
            'last_played_at' => Carbon::create(2026, 4, 7, 12, 0, 0, 'UTC'),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, 'UTC'));

        $multiplier = app(GamePlayService::class)->getMultiplier($user->fresh());

        $this->assertSame(1.0, $multiplier);

        $streak = $user->fresh()->streak;
        $this->assertSame(0, $streak->streak_count);
        $this->assertSame('1.00', $streak->multiplier);
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Player',
            'email' => $email,
            'password' => 'password123',
            'coins' => 1000,
        ]);
    }
}
