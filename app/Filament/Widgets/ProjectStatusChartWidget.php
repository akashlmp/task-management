<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ProjectStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Project Status Breakdown';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = auth()->user();
        $query = Project::query();

        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhereHas('allocations', fn (Builder $aq) => $aq->where('employee_id', $user->id));
            });
        }

        $statuses = [
            'planning' => ['label' => 'Planning', 'color' => '#94a3b8'],
            'in_progress' => ['label' => 'In Progress', 'color' => '#3b82f6'],
            'on_hold' => ['label' => 'On Hold', 'color' => '#f59e0b'],
            'completed' => ['label' => 'Completed', 'color' => '#10b981'],
            'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444'],
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($statuses as $statusKey => $config) {
            $labels[] = $config['label'];
            $data[] = (clone $query)->where('status', $statusKey)->count();
            $colors[] = $config['color'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Projects',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
