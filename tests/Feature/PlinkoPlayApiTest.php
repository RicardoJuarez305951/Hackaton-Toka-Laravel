<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameHistory;
use App\Models\User;
use App\Services\SecureRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlinkoPlayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plinko_play_returns_unified_payload_and_persists_path_based_meta(): void
    {
        $this->bindRandomSequence([1, 1, 1, 1, 1, 1]);

        $user = User::create([
            'name' => 'Plinko User',
            'email' => 'plinko@example.com',
            'password' => 'password123',
            'coins' => 1000,
        ]);

        $game = Game::create([
            'slug' => 'plinko',
            'name' => 'Plinko',
            'description' => 'Plinko test game',
            'icon' => '/images/plinko.png',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/plinko/play', [
            'user_id' => $user->id,
            'bet' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('system.status', 'success')
            ->assertJsonPath('game.type', 'plinko')
            ->assertJsonPath('game.visual_data.final_slot', 6)
            ->assertJsonPath('game.visual_data.path.0', 1)
            ->assertJsonPath('prize', 50)
            ->assertJsonPath('balance', 1040);

        $payload = $response->json();
        $this->assertSame([1, 1, 1, 1, 1, 1], $payload['game']['visual_data']['path']);
        $this->assertSame(50, $payload['game']['payout']);
        $this->assertSame(40, $payload['user']['diff']);
        $this->assertSame(6, $payload['slot_index']);
        $this->assertSame(6, $payload['spline']['final_slot']);
        $this->assertCount(6, $payload['spline']['path']);
        $this->assertNotEmpty($payload['spline']['keyframes']);

        $history = GameHistory::query()->first();
        $this->assertNotNull($history);
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame($game->id, $history->game_id);
        $this->assertSame([1, 1, 1, 1, 1, 1], $history->meta['path'] ?? null);
        $this->assertSame(6, $history->meta['slot_index'] ?? null);
        $this->assertIsArray($history->meta['spline'] ?? null);
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
