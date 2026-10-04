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
        $admin = User::updateOrCreate(
            ['email' => 'admin@sso.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->profile()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'full_name' => 'System Administrator',
                'phone_number' => '0901234567',
            ]
        );

        // 2. Regular User
        $regularUser = User::updateOrCreate(
            ['email' => 'user@sso.local'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('User@123456'),
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $regularUser->profile()->updateOrCreate(
            ['user_id' => $regularUser->id],
            [
                'full_name' => 'John Doe',
                'phone_number' => '0912345678',
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

        // -----------------------------------------------------------------------
        // HRM Foundation: Position Types & Departments
        // -----------------------------------------------------------------------
        $this->call([
            PositionTypeSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
