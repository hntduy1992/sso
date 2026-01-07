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

        //user|admin|super
        Permission::create(['name' => 'dashboard.view']);

        //super
        Permission::create(['name' => 'user.list.super']);
        Permission::create(['name' => 'user.create.super']);
        Permission::create(['name' => 'user.update.super']);
        Permission::create(['name' => 'user.view.super']);
        Permission::create(['name' => 'user.delete.super']);
        Permission::create(['name' => 'user.reset-password.super']);

        Permission::create(['name' => 'app.setting.update']);


        //user
        Permission::create(['name' => 'user.create']);
        Permission::create(['name' => 'user.update']);
        Permission::create(['name' => 'user.reset-password']);
        Permission::create(['name' => 'user.list']);
        Permission::create(['name' => 'user.view']);
        Permission::create(['name' => 'user.delete']);

        //department
        Permission::create(['name' => 'department.list']);
        Permission::create(['name' => 'department.view.user']);
        Permission::create(['name' => 'department.create']);
        Permission::create(['name' => 'department.update']);
        Permission::create(['name' => 'department.delete']);

        $superAdminRole = Role::create(['name' => 'super']);
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        $superAdminRole->givePermissionTo(Permission::all());
        $adminRole->givePermissionTo([
            'dashboard.view',
            'user.create',
            'user.update',
            'user.list',
            'user.view',
            'user.reset-password',
            'user.delete',
            'department.list',
            'department.view.user',
            'department.create',
            'department.update',
            'department.delete',
        ]);
        $userRole->givePermissionTo([
            'dashboard.view',
            'department.view.user',
            'user.reset-password',
        ]);


        $userSuperAdmin->assignRole($superAdminRole);
        $userAdmin->assignRole($adminRole);
        $user->assignRole($userRole);

    }
}
