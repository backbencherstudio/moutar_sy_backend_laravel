<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionGroups = [
            'admin' => [
                'admin.register',
                'admin.index',
                'admin.edit',
                'admin.update',
                'admin.destroy',
            ],
            'role' => [
                'admin.role.index',
                'admin.role.store',
                'admin.role.edit',
                'admin.role.update',
            ],
            'permission' => [
                'admin.permission.index',
                'admin.permission.store',
                'admin.permission.edit',
                'admin.permission.update',
            ],
            'exchange' => [
                'admin.exchange.index',
                'admin.exchange.store',
                'admin.exchange.edit',
                'admin.exchange.update',
                'admin.exchange.destroy',
            ],
            'setting' => [
                'admin.setting.index',
                'admin.setting.update',
            ],
            'mail' => [
                'admin.mail.send',
            ],
        ];


        foreach ($permissionGroups as $group => $permissions) {
            foreach ($permissions as $permissionName) {
                Permission::updateOrCreate(
                    [
                        'name' => $permissionName,
                        'guard_name' => 'api'
                    ],
                    [
                        'group_name' => $group
                    ]
                );
            }
        }

        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'api')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::where('guard_name', 'api')->get());
        }

        $this->command->info('Route-wise permissions seeded successfully!');
    }
}
