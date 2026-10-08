<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $isEmployee = $user && ! $user->hasRole(['admin', 'manager']);

        // Projects query scoping
        $projectsQuery = Project::query();
        if ($isEmployee) {
            $projectsQuery->where(function (Builder $q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhereHas('allocations', fn (Builder $aq) => $aq->where('employee_id', $user->id));
            });
        }

        // Tasks query scoping
        $tasksQuery = Task::query();
        if ($isEmployee) {
            $tasksQuery->where('assigned_employee_id', $user->id);
        }

        $totalProjects = (clone $projectsQuery)->count();
        $activeProjects = (clone $projectsQuery)->whereNotIn('status', ['completed', 'cancelled'])->count();
        $completedProjects = (clone $projectsQuery)->where('status', 'completed')->count();

        $totalEmployees = User::where('status', 'active')->count();

        $totalTasks = (clone $tasksQuery)->count();
        $completedTasks = (clone $tasksQuery)->where('status', 'completed')->count();
        $pendingTasks = (clone $tasksQuery)->whereIn('status', ['pending', 'in_progress', 'on_hold'])->count();
        $overdueTasks = (clone $tasksQuery)->overdue()->count();

        return [
            Stat::make('Total Projects', $totalProjects)
                ->description($activeProjects . ' active in pipeline')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary')
                ->chart([3, 5, 7, 6, $totalProjects]),

            Stat::make('Active Projects', $activeProjects)
                ->description('In planning or execution')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->chart([2, 4, 3, 5, $activeProjects]),

            Stat::make('Completed Projects', $completedProjects)
                ->description('Successfully delivered')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([1, 2, 2, 3, $completedProjects]),

            Stat::make('Total Employees', $totalEmployees)
                ->description('Active workforce')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            Stat::make('Total Tasks', $totalTasks)
                ->description('Across assigned projects')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary')
                ->chart([5, 8, 12, 10, $totalTasks]),

            Stat::make('Completed Tasks', $completedTasks)
                ->description('Finished milestones')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([2, 4, 6, 8, $completedTasks]),

            Stat::make('Pending Tasks', $pendingTasks)
                ->description('In progress & queued')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([4, 6, 5, 7, $pendingTasks]),

            Stat::make('Overdue Tasks', $overdueTasks)
                ->description($overdueTasks > 0 ? 'Requires immediate attention' : 'All milestones on schedule')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueTasks > 0 ? 'danger' : 'success')
                ->chart($overdueTasks > 0 ? [1, 2, 3, 2, $overdueTasks] : [0, 0, 0, 0, 0]),
        ];
    }
}
