<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = User::where('email', 'manager@gmail.com')->first();
        $leader = User::where('email', 'leader@gmail.com')->first();
        $alex = User::where('email', 'alex@gmail.com')->first();
        $elena = User::where('email', 'elena@gmail.com')->first();
        $marcus = User::where('email', 'marcus@gmail.com')->first();

        // 1. Frontend & UI Engineering
        $frontendTeam = Team::firstOrCreate(
            ['name' => 'Frontend & UI Engineering'],
            [
                'description' => 'Responsible for client-facing interfaces, design system components, and usability.',
                'manager_id' => $manager?->id,
                'team_leader_id' => $leader?->id,
                'status' => 'active',
            ]
        );

        if ($alex && $elena) {
            $frontendTeam->members()->syncWithoutDetaching([
                $alex->id => ['joined_at' => '2024-03-01', 'is_active' => true],
                $elena->id => ['joined_at' => '2024-03-15', 'is_active' => true],
            ]);
        }

        // 2. Backend & Core Systems
        $backendTeam = Team::firstOrCreate(
            ['name' => 'Backend & Core Systems'],
            [
                'description' => 'Responsible for microservices, database optimizations, APIs, and cloud infrastructure.',
                'manager_id' => $manager?->id,
                'team_leader_id' => $leader?->id,
                'status' => 'active',
            ]
        );

        if ($alex && $marcus) {
            $backendTeam->members()->syncWithoutDetaching([
                $marcus->id => ['joined_at' => '2024-04-01', 'is_active' => true],
                $alex->id => ['joined_at' => '2024-03-10', 'is_active' => true],
            ]);
        }

        // 3. Quality Assurance & Support
        $qaTeam = Team::firstOrCreate(
            ['name' => 'Quality Assurance & Operations'],
            [
                'description' => 'Responsible for test automation, regression testing, reliability, and incident support.',
                'manager_id' => $manager?->id,
                'team_leader_id' => $leader?->id,
                'status' => 'active',
            ]
        );

        if ($elena && $marcus) {
            $qaTeam->members()->syncWithoutDetaching([
                $elena->id => ['joined_at' => '2024-03-20', 'is_active' => true],
                $marcus->id => ['joined_at' => '2024-04-05', 'is_active' => true],
            ]);
        }
    }
}
