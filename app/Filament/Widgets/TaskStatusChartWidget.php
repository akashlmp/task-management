<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class TaskStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Task Status Distribution';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = auth()->user();

        $query = Task::query();

        if ($user && ! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->whereHas('project', function (Builder $pq) use ($user) {
                    $pq->where('manager_id', $user->id)
                        ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
                });
            } elseif ($user->hasRole('team_leader')) {
                $query->where(function (Builder $q) use ($user) {
                    $q->whereHas('project.team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id))
                        ->orWhereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id));
                });
            } else {
                $query->where(function (Builder $q) use ($user) {
                    $q->whereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id))
                        ->orWhereHas('project.members', fn (Builder $mq) => $mq->where('users.id', $user->id));
                });
            }
        }

        $counts = [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'assigned' => (clone $query)->where('status', 'assigned')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'blocked' => (clone $query)->where('status', 'blocked')->count(),
            'review' => (clone $query)->where('status', 'review')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => array_values($counts),
                    'backgroundColor' => [
                        '#9ca3af', // gray for pending
                        '#38bdf8', // light blue for assigned
                        '#f59e0b', // amber for in_progress
                        '#ef4444', // red for blocked
                        '#8b5cf6', // purple for review
                        '#10b981', // green for completed
                    ],
                ],
            ],
            'labels' => ['Pending', 'Assigned', 'In Progress', 'Blocked', 'Review', 'Completed'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
