<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = User::where('email', 'manager@gmail.com')->first();
        $leader = User::where('email', 'leader@gmail.com')->first();

        $departments = [
            [
                'name' => 'Software Engineering',
                'description' => 'Core product engineering, backend APIs, and system architecture.',
                'status' => 'active',
                'department_head_id' => $manager?->id,
            ],
            [
                'name' => 'Product & Design',
                'description' => 'User experience, visual UI design, and product roadmap definition.',
                'status' => 'active',
                'department_head_id' => $leader?->id,
            ],
            [
                'name' => 'Quality Assurance & DevOps',
                'description' => 'Automation testing, continuous integration, and infrastructure stability.',
                'status' => 'active',
                'department_head_id' => $manager?->id,
            ],
            [
                'name' => 'Client Success & Operations',
                'description' => 'Client relations, implementation management, and product support.',
                'status' => 'active',
                'department_head_id' => null,
            ],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                $dept
            );
        }
    }
}
