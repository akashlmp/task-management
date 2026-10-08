<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class TaskProgressChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Task Velocity & Status Distribution';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = auth()->user();
        $query = Task::query();

        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            $query->where('assigned_employee_id', $user->id);
        }

        $categories = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'on_hold' => 'On Hold',
            'completed' => 'Completed',
        ];

        $counts = [];
        $avgProgress = [];

        foreach ($categories as $statusKey => $label) {
            $statusTasks = (clone $query)->where('status', $statusKey);
            $counts[] = (clone $statusTasks)->count();
            $avgProgress[] = (int) round((clone $statusTasks)->avg('progress') ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Tasks',
                    'data' => $counts,
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Average Progress (%)',
                    'data' => $avgProgress,
                    'backgroundColor' => '#10b981',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_values($categories),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
