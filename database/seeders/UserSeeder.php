<?php

namespace Database\Seeders;

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
        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-001',
                'phone' => '+1 (555) 010-0001',
                'status' => 'active',
                'joined_at' => '2024-01-01',
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@gmail.com'],
            [
                'name' => 'Sarah Jenkins (Manager)',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-002',
                'phone' => '+1 (555) 010-0002',
                'status' => 'active',
                'joined_at' => '2024-01-15',
            ]
        );
        $manager->syncRoles(['manager']);

        // 3. Team Leader User
        $leader = User::firstOrCreate(
            ['email' => 'leader@gmail.com'],
            [
                'name' => 'David Chen (Team Leader)',
                'password' => Hash::make('password'),
                'employee_code' => 'EMP-003',
                'phone' => '+1 (555) 010-0003',
                'status' => 'active',
                'joined_at' => '2024-02-01',
            ]
        );
        $leader->syncRoles(['team_leader']);

        // 4. Team Members
        $members = [
            [
                'name' => 'Alex Miller',
                'email' => 'alex@gmail.com',
                'employee_code' => 'EMP-004',
                'phone' => '+1 (555) 010-0004',
                'joined_at' => '2024-03-01',
            ],
            [
                'name' => 'Elena Rostova',
                'email' => 'elena@gmail.com',
                'employee_code' => 'EMP-005',
                'phone' => '+1 (555) 010-0005',
                'joined_at' => '2024-03-15',
            ],
            [
                'name' => 'Marcus Vance',
                'email' => 'marcus@gmail.com',
                'employee_code' => 'EMP-006',
                'phone' => '+1 (555) 010-0006',
                'joined_at' => '2024-04-01',
            ],
        ];

        foreach ($members as $memberData) {
            $member = User::firstOrCreate(
                ['email' => $memberData['email']],
                [
                    'name' => $memberData['name'],
                    'password' => Hash::make('password'),
                    'employee_code' => $memberData['employee_code'],
                    'phone' => $memberData['phone'],
                    'status' => 'active',
                    'joined_at' => $memberData['joined_at'],
                ]
            );
            $member->syncRoles(['team_member']);
        }
    }
}
