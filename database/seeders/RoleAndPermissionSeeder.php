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
            // Users / Employees
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Departments
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',

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

            // Allocations
            'allocations.view',
            'allocations.create',
            'allocations.update',
            'allocations.delete',

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

        // 2. Manager Role - Full access to projects, employees, allocations, tasks, reports, analytics
        $managerRole = Role::findOrCreate('manager', 'web');
        $managerRole->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
            'departments.view',
            'departments.create',
            'departments.update',
            'teams.view',
            'teams.create',
            'teams.update',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.complete',
            'allocations.view',
            'allocations.create',
            'allocations.update',
            'allocations.delete',
            'reports.view',
            'performance.view',
            'sla.view',
        ]);

        // 3. Employee Role - Restricted to assigned projects & tasks
        $employeeRole = Role::findOrCreate('employee', 'web');
        $employeeRole->syncPermissions([
            'projects.view',
            'tasks.view',
            'tasks.update',
            'tasks.complete',
        ]);

        // 4. Team Member Role (Alias to Employee)
        $memberRole = Role::findOrCreate('team_member', 'web');
        $memberRole->syncPermissions([
            'projects.view',
            'tasks.view',
            'tasks.update',
            'tasks.complete',
        ]);

        // 5. Team Leader Role
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
            'allocations.view',
            'performance.view',
            'sla.view',
        ]);
    }
}
