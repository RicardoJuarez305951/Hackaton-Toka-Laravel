<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GoldenTreeState;
use App\Models\HiloRound;
use App\Models\User;
use App\Services\SecureRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HiloApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hilo_start_guess_and_cashout_flow_is_stateful(): void
    {
        $this->bindRandomSequence([10, 0, 12, 1]);
        $user = $this->makeUser('hilo@example.com');
        Game::create(['slug' => 'hilo', 'name' => 'Hilo', 'description' => 'desc', 'icon' => '/images/hilo.png', 'is_active' => true]);

        $start = $this->postJson('/api/hilo/start', ['user_id' => $user->id, 'bet' => 10]);
        $start->assertStatus(200)
            ->assertJsonPath('system.status', 'success')
            ->assertJsonPath('game.visual_data.current_card.value', 10)
            ->assertJsonPath('balance', 990);

        $guess = $this->postJson('/api/hilo/guess', ['user_id' => $user->id, 'direction' => 'higher']);
        $guess->assertStatus(200)
            ->assertJsonPath('game.is_win', true)
            ->assertJsonPath('game.visual_data.next_card.value', 12)
            ->assertJsonPath('game.visual_data.current_multiplier', 2.7)
            ->assertJsonPath('game.visual_data.potential_payout', 27);

        $cashout = $this->postJson('/api/hilo/cashout', ['user_id' => $user->id]);
        $cashout->assertStatus(200)
            ->assertJsonPath('game.payout', 27)
            ->assertJsonPath('balance', 1017)
            ->assertJsonPath('user.diff', 17);

        $round = HiloRound::query()->first();
        $this->assertFalse($round->is_active);
    }

    public function test_hilo_rejects_impossible_direction_for_ace(): void
    {
        $user = $this->makeUser('ace@example.com');
        Game::create(['slug' => 'hilo', 'name' => 'Hilo', 'description' => 'desc', 'icon' => '/images/hilo.png', 'is_active' => true]);

        HiloRound::create([
            'user_id' => $user->id,
            'bet' => 10,
            'starting_balance' => 1000,
            'current_rank' => 14,
            'current_suit' => 'spades',
            'streak' => 0,
            'current_multiplier' => 1.0,
            'potential_payout' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/hilo/guess', ['user_id' => $user->id, 'direction' => 'higher']);

        $response->assertStatus(422)
            ->assertJsonPath('system.status', 'error');
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Hilo User',
            'email' => $email,
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
