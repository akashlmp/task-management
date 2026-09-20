<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
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

        $frontendTeam = Team::where('name', 'Frontend & UI Engineering')->first();
        $backendTeam = Team::where('name', 'Backend & Core Systems')->first();
        $qaTeam = Team::where('name', 'Quality Assurance & Operations')->first();

        // 1. Next-Gen Client Portal
        $portal = Project::firstOrCreate(
            ['code' => 'PRJ-PORTAL'],
            [
                'name' => 'Next-Gen Client Portal',
                'description' => 'Modern self-service customer portal with interactive dashboards, profile management, and audit trails.',
                'manager_id' => $manager?->id,
                'team_id' => $frontendTeam?->id,
                'start_date' => '2024-03-01',
                'due_date' => '2024-07-31',
                'status' => 'active',
                'priority' => 'high',
            ]
        );

        if ($leader && $alex && $elena) {
            $portal->members()->syncWithoutDetaching([
                $leader->id => ['role' => 'lead'],
                $alex->id => ['role' => 'developer'],
                $elena->id => ['role' => 'designer'],
            ]);
        }

        // 2. Core Microservices API Gateway
        $gateway = Project::firstOrCreate(
            ['code' => 'PRJ-GATEWAY'],
            [
                'name' => 'Core Microservices API Gateway',
                'description' => 'High-throughput, event-driven API gateway providing authentication, rate-limiting, and centralized logging.',
                'manager_id' => $manager?->id,
                'team_id' => $backendTeam?->id,
                'start_date' => '2024-02-15',
                'due_date' => '2024-06-30',
                'status' => 'active',
                'priority' => 'critical',
            ]
        );

        if ($leader && $alex && $marcus) {
            $gateway->members()->syncWithoutDetaching([
                $leader->id => ['role' => 'lead'],
                $alex->id => ['role' => 'developer'],
                $marcus->id => ['role' => 'developer'],
            ]);
        }

        // 3. Automated End-to-End Regression Suite
        $e2e = Project::firstOrCreate(
            ['code' => 'PRJ-E2E'],
            [
                'name' => 'Automated End-to-End Regression Suite',
                'description' => 'Comprehensive end-to-end regression automation covering critical business workflows and SLA validation.',
                'manager_id' => $manager?->id,
                'team_id' => $qaTeam?->id,
                'start_date' => '2024-04-01',
                'due_date' => '2024-08-15',
                'status' => 'active',
                'priority' => 'medium',
            ]
        );

        if ($leader && $elena && $marcus) {
            $e2e->members()->syncWithoutDetaching([
                $leader->id => ['role' => 'lead'],
                $elena->id => ['role' => 'qa'],
                $marcus->id => ['role' => 'qa'],
            ]);
        }
    }
}
