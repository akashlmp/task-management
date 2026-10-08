<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $engDept = Department::where('name', 'Software Engineering')->first();
        $designDept = Department::where('name', 'Product & Design')->first();
        $qaDept = Department::where('name', 'Quality Assurance & DevOps')->first();

        // 1. Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-001',
                'phone' => '+1 (555) 010-0001',
                'designation' => 'System Administrator',
                'department_id' => $engDept?->id,
                'status' => 'active',
                'joined_at' => '2024-01-01',
                'joining_date' => '2024-01-01',
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. Manager User
        $manager = User::updateOrCreate(
            ['email' => 'manager@gmail.com'],
            [
                'name' => 'Sarah Jenkins (Manager)',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-002',
                'phone' => '+1 (555) 010-0002',
                'designation' => 'Senior Project Manager',
                'department_id' => $engDept?->id,
                'status' => 'active',
                'joined_at' => '2024-01-15',
                'joining_date' => '2024-01-15',
            ]
        );
        $manager->syncRoles(['manager']);

        // 3. Team Leader User
        $leader = User::updateOrCreate(
            ['email' => 'leader@gmail.com'],
            [
                'name' => 'David Chen (Team Leader)',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-003',
                'phone' => '+1 (555) 010-0003',
                'designation' => 'Lead Full-Stack Architect',
                'department_id' => $engDept?->id,
                'status' => 'active',
                'joined_at' => '2024-02-01',
                'joining_date' => '2024-02-01',
            ]
        );
        $leader->syncRoles(['team_leader']);

        // 4. Dedicated Employee User (for testing employee-scoped login)
        $employee = User::updateOrCreate(
            ['email' => 'employee@gmail.com'],
            [
                'name' => 'John Developer (Employee)',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-004',
                'phone' => '+1 (555) 010-0004',
                'designation' => 'Software Engineer',
                'department_id' => $engDept?->id,
                'status' => 'active',
                'joined_at' => '2024-02-15',
                'joining_date' => '2024-02-15',
            ]
        );
        $employee->syncRoles(['employee', 'team_member']);

        // 5. Team Members / Employees
        $members = [
            [
                'name' => 'Alex Miller',
                'email' => 'alex@gmail.com',
                'employee_code' => 'EMP-005',
                'phone' => '+1 (555) 010-0005',
                'designation' => 'Frontend Engineer',
                'department_id' => $engDept?->id,
                'joined_at' => '2024-03-01',
            ],
            [
                'name' => 'Elena Rostova',
                'email' => 'elena@gmail.com',
                'employee_code' => 'EMP-006',
                'phone' => '+1 (555) 010-0006',
                'designation' => 'Senior UI/UX Designer',
                'department_id' => $designDept?->id,
                'joined_at' => '2024-03-15',
            ],
            [
                'name' => 'Marcus Vance',
                'email' => 'marcus@gmail.com',
                'employee_code' => 'EMP-007',
                'phone' => '+1 (555) 010-0007',
                'designation' => 'QA Automation Engineer',
                'department_id' => $qaDept?->id,
                'joined_at' => '2024-04-01',
            ],
        ];

        foreach ($members as $memberData) {
            $member = User::updateOrCreate(
                ['email' => $memberData['email']],
                [
                    'name' => $memberData['name'],
                    'password' => Hash::make('password'),
                    'employee_code' => $memberData['employee_code'],
                    'phone' => $memberData['phone'],
                    'designation' => $memberData['designation'],
                    'department_id' => $memberData['department_id'],
                    'status' => 'active',
                    'joined_at' => $memberData['joined_at'],
                    'joining_date' => $memberData['joined_at'],
                ]
            );
            $member->syncRoles(['employee', 'team_member']);
        }

        // Link Department Heads
        if ($engDept && $manager) {
            $engDept->update(['department_head_id' => $manager->id]);
        }
        if ($designDept && $leader) {
            $designDept->update(['department_head_id' => $leader->id]);
        }
    }
}
