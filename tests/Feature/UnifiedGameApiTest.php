<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameHistory;
use App\Models\User;
use App\Services\SecureRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedGameApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ruleta_returns_unified_envelope_and_legacy_fields(): void
    {
        $this->bindRandomSequence([4]);
        $user = $this->makeUser();
        Game::create(['slug' => 'ruleta', 'name' => 'Ruleta', 'description' => 'desc', 'icon' => '/images/ruleta.png', 'is_active' => true]);

        $response = $this->postJson('/api/ruleta/play', ['user_id' => $user->id, 'bet' => 10]);

        $response->assertStatus(200)
            ->assertJsonPath('system.status', 'success')
            ->assertJsonPath('game.type', 'ruleta')
            ->assertJsonPath('game.visual_data.win_index', 4)
            ->assertJsonPath('game.payout', 100)
            ->assertJsonPath('prize', 100)
            ->assertJsonPath('win_index', 4)
            ->assertJsonPath('balance', 1090);

        $this->assertSame('ruleta', GameHistory::query()->first()->meta['type'] ?? null);
    }

    public function test_rasca_returns_steps_and_final_level_in_visual_data(): void
    {
        $this->bindRandomSequence([1, 50, 41]);
        $user = $this->makeUser('rasca@example.com');
        Game::create(['slug' => 'rasca', 'name' => 'Rasca', 'description' => 'desc', 'icon' => '/images/rasca.png', 'is_active' => true]);

        $response = $this->postJson('/api/rasca/play', ['user_id' => $user->id, 'bet' => 10]);

        $response->assertStatus(200)
            ->assertJsonPath('system.status', 'success')
            ->assertJsonPath('game.type', 'rasca')
            ->assertJsonPath('game.visual_data.final_level', 2)
            ->assertJsonPath('game.payout', 10)
            ->assertJsonPath('balance', 1000)
            ->assertJsonPath('prize', 10);

        $payload = $response->json();
        $this->assertCount(3, $payload['game']['visual_data']['steps']);
        $this->assertFalse($payload['game']['visual_data']['steps'][2]['passed']);
        $this->assertSame(0, $payload['user']['diff']);
    }

    private function makeUser(string $email = 'user@example.com'): User
    {
        return User::create([
            'name' => 'Player',
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
