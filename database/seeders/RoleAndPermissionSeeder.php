<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions list
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Teams
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',

            // Projects
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',

            // Tasks
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.complete',

            // Reports & Performance
            'reports.view',
            'performance.view',
            'sla.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 1. Admin Role - Has all permissions
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions(Permission::all());

        // 2. Manager Role
        $managerRole = Role::findOrCreate('manager', 'web');
        $managerRole->syncPermissions([
            'users.view',
            'teams.view',
            'teams.create',
            'teams.update',
            'projects.view',
            'projects.create',
            'projects.update',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.complete',
            'reports.view',
            'performance.view',
            'sla.view',
        ]);

        // 3. Team Leader Role
        $leaderRole = Role::findOrCreate('team_leader', 'web');
        $leaderRole->syncPermissions([
            'users.view',
            'teams.view',
            'projects.view',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.assign',
            'tasks.complete',
            'performance.view',
            'sla.view',
        ]);

        // 4. Team Member Role
        $memberRole = Role::findOrCreate('team_member', 'web');
        $memberRole->syncPermissions([
            'teams.view',
            'projects.view',
            'tasks.view',
            'tasks.update',
            'tasks.complete',
        ]);
    }
}
