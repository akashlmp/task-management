<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class SlaComplianceChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'SLA Compliance by Priority';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = auth()->user();

        $query = Task::query()->with('slaLog');

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

        $priorities = ['critical', 'high', 'medium', 'low'];
        $compliantData = [];
        $breachedData = [];

        foreach ($priorities as $priority) {
            $priorityTasks = (clone $query)->where('priority', $priority)->get();

            $compliant = 0;
            $breached = 0;

            foreach ($priorityTasks as $task) {
                $log = $task->slaLog;
                if (! $log) {
                    $compliant++;
                    continue;
                }

                if ($log->response_breached || $log->resolution_breached) {
                    $breached++;
                } else {
                    $compliant++;
                }
            }

            $compliantData[] = $compliant;
            $breachedData[] = $breached;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Compliant / On-Track',
                    'data' => $compliantData,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Breached',
                    'data' => $breachedData,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => ['Critical', 'High', 'Medium', 'Low'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
