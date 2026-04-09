<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GoldenTreeState;
use App\Models\User;
use App\Services\SecureRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoldenTreeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_goldentree_state_triggers_controlled_event_after_four_hours(): void
    {
        Carbon::setTestNow('2026-04-08 12:00:00');
        $this->bindRandomSequence([1, 2]);

        $user = $this->makeUser();
        Game::create(['slug' => 'goldentree', 'name' => 'Golden Tree', 'description' => 'desc', 'icon' => '/images/goldentree.png', 'is_active' => true]);
        GoldenTreeState::create([
            'user_id' => $user->id,
            'stage' => 0,
            'growth_seconds' => 0,
            'banked_tp' => 0,
            'total_generated' => 0,
            'events_attended' => 0,
            'last_processed_at' => Carbon::now()->subHours(4),
            'last_event_check_at' => Carbon::now()->subHours(4),
        ]);

        $response = $this->getJson('/api/goldentree/state?user_id='.$user->id);

        $response->assertStatus(200)
            ->assertJsonPath('system.status', 'success')
            ->assertJsonPath('game.type', 'goldentree')
            ->assertJsonPath('game.visual_data.active_event.id', 'storm');
    }

    public function test_goldentree_collect_applies_commission_and_resets_bank(): void
    {
        Carbon::setTestNow('2026-04-08 12:00:00');

        $user = $this->makeUser();
        $game = Game::create(['slug' => 'goldentree', 'name' => 'Golden Tree', 'description' => 'desc', 'icon' => '/images/goldentree.png', 'is_active' => true]);
        GoldenTreeState::create([
            'user_id' => $user->id,
            'stage' => 3,
            'growth_seconds' => 2000,
            'banked_tp' => 100,
            'total_generated' => 100,
            'events_attended' => 0,
            'last_processed_at' => Carbon::now(),
            'last_event_check_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/goldentree/collect', ['user_id' => $user->id]);

        $response->assertStatus(200)
            ->assertJsonPath('game.payout', 85)
            ->assertJsonPath('balance', 1085)
            ->assertJsonPath('game.visual_data.collection.commission', 15);

        $this->assertSame(0, GoldenTreeState::query()->first()->banked_tp);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Tree User',
            'email' => 'tree@example.com',
            'password' => 'password123',
            'coins' => 1000,
        ]);
    }

    private function bindRandomSequence(array $values): void
    {
        $this->app->instance(SecureRandomService::class, new class($values) extends SecureRandomService {
            public function __construct(private array $values)
            {
            }

            public function int(int $min, int $max): int
            {
                if ($this->values === []) {
                    throw new \RuntimeException('Fake RNG exhausted.');
                }

                $value = array_shift($this->values);
                if ($value < $min || $value > $max) {
                    throw new \RuntimeException("Fake RNG value {$value} outside range {$min}-{$max}.");
                }

                return $value;
            }
        });
    }
}
