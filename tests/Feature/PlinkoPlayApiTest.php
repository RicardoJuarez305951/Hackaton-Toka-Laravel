<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlinkoPlayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plinko_play_returns_extended_spline_and_persists_meta(): void
    {
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
            ->assertJsonStructure([
                'success',
                'prize',
                'multiplier',
                'applied_multiplier',
                'slot_index',
                'balance',
                'spline' => [
                    'positions',
                    'final_slot',
                    'keyframes',
                    'duration_ms',
                    'seed',
                    'board',
                    'physics',
                ],
            ]);

        $payload = $response->json();

        $this->assertSame($payload['slot_index'], $payload['spline']['final_slot']);
        $this->assertNotEmpty($payload['spline']['keyframes']);
        $this->assertIsArray($payload['spline']['positions']);
        $this->assertCount(7, $payload['spline']['positions']);

        $history = GameHistory::query()->first();
        $this->assertNotNull($history);
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame($game->id, $history->game_id);
        $this->assertSame('plinko_v2', $history->meta['type'] ?? null);
        $this->assertSame($payload['slot_index'], $history->meta['slot_index'] ?? null);
        $this->assertSame($payload['spline']['seed'], $history->meta['seed'] ?? null);
        $this->assertIsArray($history->meta['keyframes'] ?? null);
    }
}

