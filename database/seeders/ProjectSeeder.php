<?php

namespace Database\Seeders;

use App\Models\EmployeeProjectAllocation;
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
        $manager = User::where('email', 'manager@gmail.com')->first();
        $leader = User::where('email', 'leader@gmail.com')->first();
        $employee = User::where('email', 'employee@gmail.com')->first();
        $alex = User::where('email', 'alex@gmail.com')->first();
        $elena = User::where('email', 'elena@gmail.com')->first();
        $marcus = User::where('email', 'marcus@gmail.com')->first();

        $frontendTeam = Team::where('name', 'Frontend & UI Engineering')->first();
        $backendTeam = Team::where('name', 'Backend & Core Systems')->first();
        $qaTeam = Team::where('name', 'Quality Assurance & Operations')->first();

        // 1. Next-Gen Client Portal
        $portal = Project::updateOrCreate(
            ['code' => 'PRJ-PORTAL'],
            [
                'name' => 'Next-Gen Client Portal',
                'description' => 'Modern self-service customer portal with interactive dashboards, profile management, and audit trails.',
                'client_company' => 'Acme Global Corp',
                'project_manager_id' => $manager?->id,
                'manager_id' => $manager?->id,
                'team_id' => $frontendTeam?->id,
                'start_date' => now()->subDays(30)->toDateString(),
                'deadline' => now()->addDays(60)->toDateString(),
                'due_date' => now()->addDays(60)->toDateString(),
                'status' => 'in_progress',
                'priority' => 'high',
                'budget' => 75000.00,
                'progress' => 45,
            ]
        );

        // 2. Core Microservices API Gateway
        $gateway = Project::updateOrCreate(
            ['code' => 'PRJ-GATEWAY'],
            [
                'name' => 'Core Microservices API Gateway',
                'description' => 'High-throughput, event-driven API gateway providing authentication, rate-limiting, and centralized logging.',
                'client_company' => 'Fintech Solutions Ltd',
                'project_manager_id' => $manager?->id,
                'manager_id' => $manager?->id,
                'team_id' => $backendTeam?->id,
                'start_date' => now()->subDays(45)->toDateString(),
                'deadline' => now()->addDays(30)->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'in_progress',
                'priority' => 'urgent',
                'budget' => 120000.00,
                'progress' => 60,
            ]
        );

        // 3. Automated End-to-End Regression Suite
        $e2e = Project::updateOrCreate(
            ['code' => 'PRJ-E2E'],
            [
                'name' => 'Automated End-to-End Regression Suite',
                'description' => 'Comprehensive end-to-end regression automation covering critical business workflows and SLA validation.',
                'client_company' => 'Retail Omnichannel Inc',
                'project_manager_id' => $manager?->id,
                'manager_id' => $manager?->id,
                'team_id' => $qaTeam?->id,
                'start_date' => now()->subDays(10)->toDateString(),
                'deadline' => now()->addDays(40)->toDateString(),
                'due_date' => now()->addDays(40)->toDateString(),
                'status' => 'planning',
                'priority' => 'medium',
                'budget' => 45000.00,
                'progress' => 10,
            ]
        );

        // 4. Completed Project for Closed Project Guard & Stats
        $legacy = Project::updateOrCreate(
            ['code' => 'PRJ-LEGACY'],
            [
                'name' => 'Legacy System Migration',
                'description' => 'Cloud migration and data modernization from on-premises SQL to distributed microservices.',
                'client_company' => 'Heritage Bank Ltd',
                'project_manager_id' => $manager?->id,
                'manager_id' => $manager?->id,
                'team_id' => $backendTeam?->id,
                'start_date' => now()->subDays(90)->toDateString(),
                'deadline' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(10)->toDateString(),
                'status' => 'completed',
                'priority' => 'low',
                'budget' => 95000.00,
                'progress' => 100,
            ]
        );

        // Seed Allocations
        $allocations = [
            // Portal Project
            [
                'project_id' => $portal->id,
                'employee_id' => $alex?->id,
                'allocation_percentage' => 50,
                'role' => 'Frontend Lead',
                'start_date' => $portal->start_date,
                'end_date' => $portal->deadline,
            ],
            [
                'project_id' => $portal->id,
                'employee_id' => $elena?->id,
                'allocation_percentage' => 40,
                'role' => 'Lead UX Designer',
                'start_date' => $portal->start_date,
                'end_date' => $portal->deadline,
            ],
            [
                'project_id' => $portal->id,
                'employee_id' => $employee?->id,
                'allocation_percentage' => 40,
                'role' => 'Full-Stack Engineer',
                'start_date' => $portal->start_date,
                'end_date' => $portal->deadline,
            ],

            // Gateway Project
            [
                'project_id' => $gateway->id,
                'employee_id' => $alex?->id,
                'allocation_percentage' => 40,
                'role' => 'API Developer',
                'start_date' => $gateway->start_date,
                'end_date' => $gateway->deadline,
            ],
            [
                'project_id' => $gateway->id,
                'employee_id' => $marcus?->id,
                'allocation_percentage' => 50,
                'role' => 'Integration Tester',
                'start_date' => $gateway->start_date,
                'end_date' => $gateway->deadline,
            ],
            [
                'project_id' => $gateway->id,
                'employee_id' => $employee?->id,
                'allocation_percentage' => 50,
                'role' => 'Backend Engineer',
                'start_date' => $gateway->start_date,
                'end_date' => $gateway->deadline,
            ],

            // E2E Project
            [
                'project_id' => $e2e->id,
                'employee_id' => $elena?->id,
                'allocation_percentage' => 30,
                'role' => 'Design QA',
                'start_date' => $e2e->start_date,
                'end_date' => $e2e->deadline,
            ],
            [
                'project_id' => $e2e->id,
                'employee_id' => $marcus?->id,
                'allocation_percentage' => 40,
                'role' => 'QA Architect',
                'start_date' => $e2e->start_date,
                'end_date' => $e2e->deadline,
            ],
        ];

        foreach ($allocations as $alloc) {
            if ($alloc['employee_id'] && $alloc['project_id']) {
                EmployeeProjectAllocation::updateOrCreate(
                    [
                        'employee_id' => $alloc['employee_id'],
                        'project_id' => $alloc['project_id'],
                    ],
                    $alloc
                );
            }
        }
    }
}
