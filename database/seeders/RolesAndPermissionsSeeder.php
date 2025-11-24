<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Admin permissions
            'view admins',
            'create admins',
            'edit admins',
            'delete admins',

            // Role permissions
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Permission permissions
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            'assign permissions',

            // Audit log permissions
            'view audit logs',

            // Dashboard permissions
            'view dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - has all permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - can manage users and view dashboard
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view roles',
            'view permissions',
            'view audit logs',
            'view dashboard',
        ]);

        // User Manager - can only manage users
        $userManager = Role::create(['name' => 'user-manager']);
        $userManager->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'delete users',
        ]);

        // Viewer - can only view data
        $viewer = Role::create(['name' => 'viewer']);
        $viewer->givePermissionTo([
            'view users',
            'view roles',
            'view permissions',
            'view audit logs',
            'view dashboard',
        ]);

        // Create super admin users
        $superAdmins = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Super Admin 2',
                'email' => 'admin2@example.com',
            ],
            [
                'name' => 'Super Admin 3',
                'email' => 'admin3@example.com',
            ],
        ];

        foreach ($superAdmins as $data) {
            $admin = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt('password'),
                'is_admin' => true,
                'is_active' => true,
            ]);
            $admin->assignRole('super-admin');
        }

        // Create a regular admin user
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.user@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
        $adminUser->assignRole('admin');

        // Create a regular user
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_admin' => false,
            'is_active' => true,
        ]);
        $regularUser->assignRole('viewer');

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('');
        $this->command->info('Test Users:');
        $this->command->info('Super Admins: admin@example.com / password, admin2@example.com / password, admin3@example.com / password');
        $this->command->info('Admin: admin.user@example.com / password');
        $this->command->info('User: user@example.com / password');
    }
}
