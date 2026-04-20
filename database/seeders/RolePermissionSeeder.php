<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'rooms.view',
            'rooms.manage',
            'bookings.create',
            'bookings.view_own',
            'bookings.cancel_own',
            'bookings.cancel_any',
            'users.view',
            'users.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
            ]);
        }

        $roles = [
            'guest' => [
                'rooms.view',
            ],
            'employee' => [
                'rooms.view',
                'bookings.create',
                'bookings.view_own',
                'bookings.cancel_own',
            ],
            'office_manager' => [
                'rooms.view',
                'rooms.manage',
                'bookings.create',
                'bookings.view_own',
                'bookings.cancel_own',
                'bookings.cancel_any',
            ],
            'admin' => $permissions,
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
            ]);

            $permissionIds = Permission::query()
                ->whereIn('name', $rolePermissions)
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
