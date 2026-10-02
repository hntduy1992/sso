<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Admin
        User::updateOrCreate(
            ['email' => 'admin@sso.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Regular User
        User::updateOrCreate(
            ['email' => 'user@sso.local'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('User@123456'),
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 3. Suspended User
        User::updateOrCreate(
            ['email' => 'suspended@sso.local'],
            [
                'name' => 'Jane Locked',
                'password' => Hash::make('Suspended@123456'),
                'role' => 'user',
                'status' => 'suspended',
                'email_verified_at' => now(),
            ]
        );

        // 4. Client Developer
        User::updateOrCreate(
            ['email' => 'dev@sso.local'],
            [
                'name' => 'Alex Developer',
                'password' => Hash::make('Dev@123456'),
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
