<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policies = [
            [
                'name' => 'Critical Priority SLA',
                'priority' => 'critical',
                'response_time_minutes' => 15, // 15 mins
                'resolution_time_minutes' => 240, // 4 hours
                'warning_percentage' => 80, // Warning at 12m response, 192m (3.2h) resolution
                'is_active' => true,
            ],
            [
                'name' => 'High Priority SLA',
                'priority' => 'high',
                'response_time_minutes' => 60, // 1 hour
                'resolution_time_minutes' => 480, // 8 hours
                'warning_percentage' => 80, // Warning at 48m response, 384m (6.4h) resolution
                'is_active' => true,
            ],
            [
                'name' => 'Medium Priority SLA',
                'priority' => 'medium',
                'response_time_minutes' => 240, // 4 hours
                'resolution_time_minutes' => 1440, // 24 hours (1 day)
                'warning_percentage' => 80, // Warning at 192m response, 1152m (19.2h) resolution
                'is_active' => true,
            ],
            [
                'name' => 'Low Priority SLA',
                'priority' => 'low',
                'response_time_minutes' => 480, // 8 hours
                'resolution_time_minutes' => 2880, // 48 hours (2 days)
                'warning_percentage' => 80, // Warning at 384m response, 2304m (38.4h) resolution
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policyData) {
            SlaPolicy::firstOrCreate(
                ['priority' => $policyData['priority']],
                $policyData
            );
        }
    }
}
