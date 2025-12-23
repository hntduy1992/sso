<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $userSuperAdmin = User::create([
            'full_name' => 'Super Administrator',
            'username' => 'super_admin',
            'password' => Hash::make('admin')
        ]);
        $userAdmin = User::create([
            'full_name' => 'Administrator',
            'username' => 'admin',
            'password' => Hash::make('admin')
        ]);
        $user = User::create([
            'full_name' => 'User',
            'username' => 'user',
            'password' => Hash::make('user')
        ]);

        // Tạo quyền
        Permission::create(['name' => 'manage roles']);
        Permission::create(['name' => 'manage permissions']);
        Permission::create(['name' => 'manage users']);
        Permission::create(['name' => 'manage departments']);
        Permission::create(['name' => 'view dashboard']);

        // Tạo vai trò và gán quyền
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());
        $userSuperAdmin->assignRole($superAdminRole);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('view dashboard');
        $adminRole->givePermissionTo('manage users');
        $userAdmin->assignRole($adminRole);

        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo('view dashboard');
        $user->assignRole($userRole);
    }
}
