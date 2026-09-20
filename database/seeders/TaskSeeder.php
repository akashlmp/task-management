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
        $manager = User::where('employee_code', 'EMP-002')->first() ?? User::where('email', 'like', 'manager@%')->first();
        $leader = User::where('employee_code', 'EMP-003')->first() ?? User::where('email', 'like', 'leader@%')->first();
        $alex = User::where('employee_code', 'EMP-004')->first() ?? User::where('email', 'like', 'alex@%')->first();
        $elena = User::where('employee_code', 'EMP-005')->first() ?? User::where('email', 'like', 'elena@%')->first();
        $marcus = User::where('employee_code', 'EMP-006')->first() ?? User::where('email', 'like', 'marcus@%')->first();

        $portal = Project::where('code', 'PRJ-PORTAL')->first();
        $gateway = Project::where('code', 'PRJ-GATEWAY')->first();
        $e2e = Project::where('code', 'PRJ-E2E')->first();

        if (! $portal || ! $gateway || ! $e2e) {
            return;
        }

        // 1. Portal - Task 1 (Completed)
        $t1 = Task::firstOrCreate(
            ['project_id' => $portal->id, 'title' => 'Design Responsive Navigation & Header System'],
            [
                'description' => 'Build fluid navigation bar with responsive mobile sheet, accessible keyboard focus, and avatar menu.',
                'created_by' => $manager->id,
                'priority' => 'high',
                'status' => 'completed',
                'start_at' => now()->subDays(3),
                'due_at' => now()->addDays(2),
                'completed_at' => now()->subDay(),
                'estimated_minutes' => 240,
            ]
        );
        $t1->assignees()->syncWithoutDetaching([
            $elena->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subDays(3), 'is_primary' => true],
            $alex->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subDays(3), 'is_primary' => false],
        ]);
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t1->id, 'user_id' => $elena->id, 'duration_minutes' => 180],
            ['description' => 'Completed Figma component translations and CSS adjustments.', 'started_at' => now()->subDays(2)]
        );
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t1->id, 'user_id' => $alex->id, 'duration_minutes' => 60],
            ['description' => 'Reviewed accessibility guidelines and verified screen reader labels.', 'started_at' => now()->subDay()]
        );
        TaskComment::firstOrCreate(
            ['task_id' => $t1->id, 'user_id' => $elena->id, 'comment' => 'Design review approved by team lead. Merging to main branch.']
        );

        // 2. Portal - Task 2 (In Progress)
        $t2 = Task::firstOrCreate(
            ['project_id' => $portal->id, 'title' => 'Implement OAuth2 Social & SSO Authentication'],
            [
                'description' => 'Integrate Google and GitHub social login with OAuth2 state token validation and automatic user provisioning.',
                'created_by' => $leader->id,
                'priority' => 'critical',
                'status' => 'in_progress',
                'start_at' => now()->subHours(4),
                'due_at' => now()->addHours(20),
                'estimated_minutes' => 300,
            ]
        );
        $t2->assignees()->syncWithoutDetaching([
            $alex->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subHours(5), 'is_primary' => true],
        ]);
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t2->id, 'user_id' => $alex->id, 'duration_minutes' => 120],
            ['description' => 'Configured OAuth redirect handlers and token validation.', 'started_at' => now()->subHours(2)]
        );
        TaskComment::firstOrCreate(
            ['task_id' => $t2->id, 'user_id' => $alex->id, 'comment' => 'Working on token exchange and PKCE validation. Google provider works as expected.']
        );

        // 3. Portal - Task 3 (Assigned)
        $t3 = Task::firstOrCreate(
            ['project_id' => $portal->id, 'title' => 'User Profile Notification Preferences Panel'],
            [
                'description' => 'Build user settings toggle panel to control email, in-app, and push notification frequencies.',
                'created_by' => $manager->id,
                'priority' => 'medium',
                'status' => 'assigned',
                'due_at' => now()->addDays(3),
                'estimated_minutes' => 180,
            ]
        );
        $t3->assignees()->syncWithoutDetaching([
            $elena->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subHours(2), 'is_primary' => true],
        ]);

        // 4. Gateway - Task 4 (In Progress)
        $t4 = Task::firstOrCreate(
            ['project_id' => $gateway->id, 'title' => 'Distributed Token Bucket Rate Limiting Service'],
            [
                'description' => 'Implement Redis-backed sliding window rate limiter with tiered API key quotas and rate limit header responses.',
                'created_by' => $manager->id,
                'priority' => 'critical',
                'status' => 'in_progress',
                'start_at' => now()->subHours(6),
                'due_at' => now()->addHours(18),
                'estimated_minutes' => 360,
            ]
        );
        $t4->assignees()->syncWithoutDetaching([
            $marcus->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subHours(7), 'is_primary' => true],
            $alex->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subHours(7), 'is_primary' => false],
        ]);
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t4->id, 'user_id' => $marcus->id, 'duration_minutes' => 240],
            ['description' => 'Built Redis Lua script for atomic sliding window evaluation.', 'started_at' => now()->subHours(4)]
        );
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t4->id, 'user_id' => $alex->id, 'duration_minutes' => 90],
            ['description' => 'Implemented HTTP middleware and response header formatting.', 'started_at' => now()->subHours(2)]
        );
        TaskComment::firstOrCreate(
            ['task_id' => $t4->id, 'user_id' => $marcus->id, 'comment' => 'Redis script benchmarked under 2ms execution time for 10k requests.']
        );

        // 5. Gateway - Task 5 (Review)
        $t5 = Task::firstOrCreate(
            ['project_id' => $gateway->id, 'title' => 'Centralized OpenTelemetry Distributed Tracing'],
            [
                'description' => 'Instrument core HTTP and database drivers to emit W3C Trace Context and Jaeger spans.',
                'created_by' => $leader->id,
                'priority' => 'high',
                'status' => 'review',
                'start_at' => now()->subDays(2),
                'due_at' => now()->addDay(),
                'estimated_minutes' => 240,
            ]
        );
        $t5->assignees()->syncWithoutDetaching([
            $alex->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subDays(2), 'is_primary' => true],
        ]);
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t5->id, 'user_id' => $alex->id, 'duration_minutes' => 150],
            ['description' => 'Added OpenTelemetry SDK middleware and span processors.', 'started_at' => now()->subDay()]
        );
        TaskComment::firstOrCreate(
            ['task_id' => $t5->id, 'user_id' => $alex->id, 'comment' => 'Submitted PR #42 for code review. Staging collector verified.']
        );

        // 6. E2E - Task 6 (Completed)
        $t6 = Task::firstOrCreate(
            ['project_id' => $e2e->id, 'title' => 'Automated Playwright Auth Flow Validation'],
            [
                'description' => 'Write end-to-end regression tests validating login, password reset, and session expiry handling.',
                'created_by' => $leader->id,
                'priority' => 'medium',
                'status' => 'completed',
                'start_at' => now()->subDays(2),
                'due_at' => now()->addDay(),
                'completed_at' => now()->subHours(6),
                'estimated_minutes' => 180,
            ]
        );
        $t6->assignees()->syncWithoutDetaching([
            $elena->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subDays(2), 'is_primary' => true],
            $marcus->id => ['assigned_by' => $leader->id, 'assigned_at' => now()->subDays(2), 'is_primary' => false],
        ]);
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t6->id, 'user_id' => $elena->id, 'duration_minutes' => 90],
            ['description' => 'Auth flow tests written and passing in local environment.', 'started_at' => now()->subDay()]
        );
        TaskWorkLog::firstOrCreate(
            ['task_id' => $t6->id, 'user_id' => $marcus->id, 'duration_minutes' => 60],
            ['description' => 'Configured GitHub Actions workflow matrix for Chromium and Firefox.', 'started_at' => now()->subHours(10)]
        );
    }
}
