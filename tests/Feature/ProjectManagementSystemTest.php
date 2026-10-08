<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeProjectAllocation;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectManagementSystemTest extends TestCase
{
    protected User $manager;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@gmail.com')->first();
        $this->employee = User::where('email', 'employee@gmail.com')->first();
    }

    /**
     * Test Department relationships.
     */
    public function test_department_relationships(): void
    {
        $dept = Department::where('name', 'Software Engineering')->first();
        $this->assertNotNull($dept);
        $this->assertInstanceOf(User::class, $dept->departmentHead);
        $this->assertTrue($dept->employees()->count() > 0);
    }

    /**
     * Test Date Validation: deadline cannot precede start_date.
     */
    public function test_project_deadline_cannot_precede_start_date(): void
    {
        $this->expectException(ValidationException::class);

        Project::create([
            'name' => 'Invalid Date Project',
            'start_date' => '2026-05-10',
            'deadline' => '2026-05-01', // Before start_date
        ]);
    }

    /**
     * Test Task Date Validation: due_date cannot precede start_date.
     */
    public function test_task_due_date_cannot_precede_start_date(): void
    {
        $project = Project::active()->first();

        $this->expectException(ValidationException::class);

        Task::create([
            'project_id' => $project->id,
            'name' => 'Invalid Date Task',
            'start_date' => '2026-06-15',
            'due_date' => '2026-06-10', // Before start_date
        ]);
    }

    /**
     * Test Closed Project Guard: cannot create tasks on completed or cancelled projects.
     */
    public function test_closed_project_cannot_accept_new_tasks(): void
    {
        $completedProject = Project::where('status', 'completed')->first();
        $this->assertNotNull($completedProject);

        $this->expectException(ValidationException::class);

        Task::create([
            'project_id' => $completedProject->id,
            'name' => 'Task on Closed Project',
        ]);
    }

    /**
     * Test Workload Limit: total active allocation across active projects cannot exceed 100%.
     */
    public function test_workload_limit_cannot_exceed_100_percent(): void
    {
        $activeProjects = Project::active()->get();
        $this->assertTrue($activeProjects->count() >= 2);

        $testUser = User::create([
            'name' => 'Capacity Test User',
            'email' => 'capacity_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Allocate 70% on Project 1
        EmployeeProjectAllocation::create([
            'employee_id' => $testUser->id,
            'project_id' => $activeProjects[0]->id,
            'allocation_percentage' => 70,
            'role' => 'Engineer',
        ]);

        $this->assertEquals(70, $testUser->active_allocation_percentage);
        $this->assertEquals(30, $testUser->remaining_allocation_percentage);

        // Attempt to allocate 40% on Project 2 (70 + 40 = 110% > 100%)
        $this->expectException(ValidationException::class);

        EmployeeProjectAllocation::create([
            'employee_id' => $testUser->id,
            'project_id' => $activeProjects[1]->id,
            'allocation_percentage' => 40,
            'role' => 'Engineer',
        ]);
    }

    /**
     * Test Auto-Progress Sync:
     * - Completing task sets progress to 100
     * - Setting progress to 100 sets status to completed
     * - Project recalculates progress
     */
    public function test_auto_progress_sync(): void
    {
        $project = Project::create([
            'name' => 'Sync Test Project',
            'status' => 'in_progress',
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'name' => 'Sync Task 1',
            'status' => 'in_progress',
            'progress' => 50,
        ]);

        // Update status to completed
        $task->update(['status' => 'completed']);
        $this->assertEquals(100, $task->fresh()->progress);

        // Recalculate project progress
        $project->refresh();
        $this->assertEquals(100, $project->progress);

        // Create second task
        $task2 = Task::create([
            'project_id' => $project->id,
            'name' => 'Sync Task 2',
            'status' => 'in_progress',
            'progress' => 0,
        ]);

        // Average of 100 and 0 should be 50%
        $project->refresh();
        $this->assertEquals(50, $project->progress);

        // Setting progress to 100 should auto-complete status
        $task2->update(['progress' => 100]);
        $this->assertEquals('completed', $task2->fresh()->status);

        $project->refresh();
        $this->assertEquals(100, $project->progress);
    }

    /**
     * Test REST API: Authentication and endpoints
     */
    public function test_api_authentication_and_crud(): void
    {
        // 1. Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@gmail.com',
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        $headers = ['Authorization' => 'Bearer ' . $token];

        // 2. Departments
        $deptResponse = $this->getJson('/api/v1/departments', $headers);
        $deptResponse->assertStatus(200);
        $this->assertNotEmpty($deptResponse->json('data'));

        // 3. Projects
        $projResponse = $this->getJson('/api/v1/projects', $headers);
        $projResponse->assertStatus(200);
        $this->assertNotEmpty($projResponse->json('data'));

        // 4. Tasks
        $taskResponse = $this->getJson('/api/v1/tasks', $headers);
        $taskResponse->assertStatus(200);
        $this->assertNotEmpty($taskResponse->json('data'));

        // 5. Allocations
        $allocResponse = $this->getJson('/api/v1/allocations', $headers);
        $allocResponse->assertStatus(200);
        $this->assertNotEmpty($allocResponse->json('data'));
    }

    /**
     * Test REST API: Employee Role scoping & restrictions
     */
    public function test_api_employee_scoping_and_restrictions(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@gmail.com',
            'password' => 'password',
        ]);
        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Employee tasks list should only contain tasks assigned to employee
        $tasksResponse = $this->getJson('/api/v1/tasks', $headers);
        $tasksResponse->assertStatus(200);
        foreach ($tasksResponse->json('data') as $taskItem) {
            $this->assertEquals($this->employee->id, $taskItem['assigned_employee_id']);
        }

        // Employee updating progress on their own task should succeed
        $myTask = Task::where('assigned_employee_id', $this->employee->id)->first();
        if ($myTask) {
            $updateResponse = $this->putJson("/api/v1/tasks/{$myTask->id}", [
                'progress' => 85,
                'status' => 'in_progress',
            ], $headers);

            $updateResponse->assertStatus(200);
            $this->assertEquals(85, $myTask->fresh()->progress);
        }
    }

    /**
     * Test Printable Executive Report route.
     */
    public function test_printable_summary_report(): void
    {
        $response = $this->actingAs($this->manager)->get('/admin/reports/printable-summary');
        $response->assertStatus(200);
        $response->assertSee('Executive Project & Workload Report', false);
    }
}
