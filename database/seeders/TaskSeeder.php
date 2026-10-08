<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskWorkLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = User::where('email', 'manager@gmail.com')->first();
        $leader = User::where('email', 'leader@gmail.com')->first();
        $employee = User::where('email', 'employee@gmail.com')->first();
        $alex = User::where('email', 'alex@gmail.com')->first();
        $elena = User::where('email', 'elena@gmail.com')->first();
        $marcus = User::where('email', 'marcus@gmail.com')->first();

        $portal = Project::where('code', 'PRJ-PORTAL')->first();
        $gateway = Project::where('code', 'PRJ-GATEWAY')->first();
        $e2e = Project::where('code', 'PRJ-E2E')->first();

        if (! $portal || ! $gateway || ! $e2e) {
            return;
        }

        // 1. Portal - Task 1 (Completed)
        $t1 = Task::updateOrCreate(
            ['code' => 'TSK-PORTAL-01'],
            [
                'project_id' => $portal->id,
                'name' => 'Design Responsive Navigation & Header System',
                'title' => 'Design Responsive Navigation & Header System',
                'description' => 'Build fluid navigation bar with responsive mobile sheet, accessible keyboard focus, and avatar menu.',
                'created_by' => $manager?->id,
                'assigned_employee_id' => $elena?->id,
                'priority' => 'high',
                'status' => 'completed',
                'progress' => 100,
                'start_date' => now()->subDays(15)->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'completed_at' => now()->subDays(5),
                'estimated_hours' => 12.0,
                'actual_hours' => 10.5,
            ]
        );
        if ($elena && $alex) {
            $t1->assignees()->syncWithoutDetaching([
                $elena->id => ['assigned_by' => $leader?->id, 'assigned_at' => now()->subDays(15), 'is_primary' => true],
                $alex->id => ['assigned_by' => $leader?->id, 'assigned_at' => now()->subDays(15), 'is_primary' => false],
            ]);
        }

        // 2. Portal - Task 2 (In Progress - Assigned to Employee)
        $t2 = Task::updateOrCreate(
            ['code' => 'TSK-PORTAL-02'],
            [
                'project_id' => $portal->id,
                'name' => 'Implement OAuth2 Social & SSO Authentication',
                'title' => 'Implement OAuth2 Social & SSO Authentication',
                'description' => 'Integrate Google and GitHub social login with OAuth2 state token validation and automatic user provisioning.',
                'created_by' => $leader?->id,
                'assigned_employee_id' => $employee?->id,
                'priority' => 'urgent',
                'status' => 'in_progress',
                'progress' => 60,
                'start_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'estimated_hours' => 16.0,
                'actual_hours' => 9.5,
            ]
        );
        if ($employee) {
            $t2->assignees()->syncWithoutDetaching([
                $employee->id => ['assigned_by' => $leader?->id, 'assigned_at' => now()->subDays(5), 'is_primary' => true],
            ]);
        }

        // 3. Portal - Task 3 (Pending)
        $t3 = Task::updateOrCreate(
            ['code' => 'TSK-PORTAL-03'],
            [
                'project_id' => $portal->id,
                'name' => 'User Profile Notification Preferences Panel',
                'title' => 'User Profile Notification Preferences Panel',
                'description' => 'Build user settings toggle panel to control email, in-app, and push notification frequencies.',
                'created_by' => $manager?->id,
                'assigned_employee_id' => $alex?->id,
                'priority' => 'medium',
                'status' => 'pending',
                'progress' => 0,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'estimated_hours' => 8.0,
                'actual_hours' => 0,
            ]
        );

        // 4. Gateway - Task 4 (OVERDUE - In Progress - Assigned to Employee)
        $t4 = Task::updateOrCreate(
            ['code' => 'TSK-GATEWAY-01'],
            [
                'project_id' => $gateway->id,
                'name' => 'Distributed Token Bucket Rate Limiting Service',
                'title' => 'Distributed Token Bucket Rate Limiting Service',
                'description' => 'Implement Redis-backed sliding window rate limiter with tiered API key quotas and rate limit header responses.',
                'created_by' => $manager?->id,
                'assigned_employee_id' => $employee?->id,
                'priority' => 'urgent',
                'status' => 'in_progress',
                'progress' => 40,
                'start_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(2)->toDateString(), // OVERDUE by 2 days!
                'estimated_hours' => 20.0,
                'actual_hours' => 14.0,
            ]
        );
        if ($employee) {
            $t4->assignees()->syncWithoutDetaching([
                $employee->id => ['assigned_by' => $leader?->id, 'assigned_at' => now()->subDays(10), 'is_primary' => true],
            ]);
        }

        // 5. Gateway - Task 5 (OVERDUE - Pending)
        $t5 = Task::updateOrCreate(
            ['code' => 'TSK-GATEWAY-02'],
            [
                'project_id' => $gateway->id,
                'name' => 'Centralized OpenTelemetry Distributed Tracing',
                'title' => 'Centralized OpenTelemetry Distributed Tracing',
                'description' => 'Instrument core HTTP and database drivers to emit W3C Trace Context and Jaeger spans.',
                'created_by' => $leader?->id,
                'assigned_employee_id' => $marcus?->id,
                'priority' => 'high',
                'status' => 'pending',
                'progress' => 20,
                'start_date' => now()->subDays(8)->toDateString(),
                'due_date' => now()->subDays(1)->toDateString(), // OVERDUE by 1 day!
                'estimated_hours' => 14.0,
                'actual_hours' => 3.0,
            ]
        );

        // 6. E2E - Task 6 (In Progress)
        $t6 = Task::updateOrCreate(
            ['code' => 'TSK-E2E-01'],
            [
                'project_id' => $e2e->id,
                'name' => 'Automated Playwright Auth Flow Validation',
                'title' => 'Automated Playwright Auth Flow Validation',
                'description' => 'Write end-to-end regression tests validating login, password reset, and session expiry handling.',
                'created_by' => $leader?->id,
                'assigned_employee_id' => $marcus?->id,
                'priority' => 'medium',
                'status' => 'in_progress',
                'progress' => 30,
                'start_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'estimated_hours' => 10.0,
                'actual_hours' => 3.0,
            ]
        );

        // Recalculate parent projects progress
        $portal->recalculateProgress();
        $gateway->recalculateProgress();
        $e2e->recalculateProgress();
    }
}
