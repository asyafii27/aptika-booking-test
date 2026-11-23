<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator', 'email' => 'admin@test.com', 'role' => UserRole::Admin],
            ['name' => 'User Satu', 'email' => 'user1@test.com', 'role' => UserRole::User],
            ['name' => 'User Dua', 'email' => 'user2@test.com', 'role' => UserRole::User],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password123',
                    'role' => $user['role'],
                ],
            );
        }
    }
}
