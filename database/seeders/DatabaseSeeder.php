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

        // -----------------------------------------------------------------------
        // HRM Foundation: Position Types & Departments
        // -----------------------------------------------------------------------
        $this->call([
            PositionTypeSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
