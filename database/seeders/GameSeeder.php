<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'rasca',
                'name' => 'Rasca y Gana',
                'description' => 'Rasca las tarjetas y descubre si ganaste',
                'icon' => '/images/rasca.png',
                'is_active' => true,
            ],
            [
                'slug' => 'plinko',
                'name' => 'Plinko',
                'description' => 'Deja caer la bola y espera que caiga en el multiplicador mayor',
                'icon' => '/images/plinko.png',
                'is_active' => true,
            ],
            [
                'slug' => 'ruleta',
                'name' => 'Ruleta',
                'description' => 'Gira la ruleta y espera a que se detenga en tu premio',
                'icon' => '/images/ruleta.png',
                'is_active' => true,
            ],
            [
                'slug' => 'hilo',
                'name' => 'Hilo',
                'description' => 'Sigue el hilo y descubre tu premio',
                'icon' => '/images/hilo.png',
                'is_active' => true,
            ],
            [
                'slug' => 'goldentree',
                'name' => 'Golden Tree',
                'description' => 'Cultiva tu árbol y collecta tus ganancias',
                'icon' => '/images/goldentree.png',
                'is_active' => true,
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(
                ['slug' => $game['slug']],
                $game
            );
        }
    }
}
