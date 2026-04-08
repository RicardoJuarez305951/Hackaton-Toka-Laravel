<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Usuario Prueba 1',
                'email' => 'user1@toka.com',
                'password' => bcrypt('password123'),
                'coins' => 1000,
            ],
            [
                'name' => 'Usuario Prueba 2',
                'email' => 'user2@toka.com',
                'password' => bcrypt('password123'),
                'coins' => 1000,
            ],
            [
                'name' => 'Usuario Prueba 3',
                'email' => 'user3@toka.com',
                'password' => bcrypt('password123'),
                'coins' => 1000,
            ],
            [
                'name' => 'Usuario Prueba 4',
                'email' => 'user4@toka.com',
                'password' => bcrypt('password123'),
                'coins' => 1000,
            ],
            [
                'name' => 'Usuario Prueba 5',
                'email' => 'user5@toka.com',
                'password' => bcrypt('password123'),
                'coins' => 1000,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
