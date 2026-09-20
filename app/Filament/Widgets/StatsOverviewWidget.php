<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskSlaLog;
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

        if (! $user || $user->hasRole('admin')) {
            return $this->getAdminStats();
        }

        if ($user->hasRole('manager')) {
            return $this->getManagerStats($user);
        }

        if ($user->hasRole('team_leader')) {
            return $this->getTeamLeaderStats($user);
        }

        return $this->getMemberStats($user);
    }

    protected function getAdminStats(): array
    {
        $totalProjects = Project::count();
        $totalTasks = Task::count();
        $overdueCount = Task::overdue()->count();

        $totalEvaluated = TaskSlaLog::whereNotNull('resolved_at')->count();
        $compliantCount = TaskSlaLog::whereNotNull('resolved_at')->where('resolution_breached', false)->count();
        $complianceRate = $totalEvaluated > 0 ? (int) round(($compliantCount / $totalEvaluated) * 100) : 100;

        return [
            Stat::make('Total Projects', $totalProjects)
                ->description('Active & planned initiatives')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),

            Stat::make('Total Tasks', $totalTasks)
                ->description('Across all engineering teams')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info'),

            Stat::make('Overdue Tasks', $overdueCount)
                ->description($overdueCount > 0 ? 'Requires immediate action' : 'All tasks on schedule')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('SLA Compliance', "{$complianceRate}%")
                ->description('System-wide SLA adherence')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($complianceRate >= 90 ? 'success' : 'warning'),
        ];
    }

    protected function getManagerStats(User $user): array
    {
        $managedProjectsCount = Project::where('manager_id', $user->id)
            ->orWhereHas('team', fn (Builder $q) => $q->where('manager_id', $user->id))
            ->count();

        $activeTasks = Task::active()
            ->whereHas('project', fn (Builder $q) => $q->where('manager_id', $user->id)->orWhereHas('team', fn ($tq) => $tq->where('manager_id', $user->id)))
            ->count();

        $overdueCount = Task::overdue()
            ->whereHas('project', fn (Builder $q) => $q->where('manager_id', $user->id)->orWhereHas('team', fn ($tq) => $tq->where('manager_id', $user->id)))
            ->count();

        $resolvedLogs = TaskSlaLog::whereNotNull('resolved_at')
            ->whereHas('task.project', fn (Builder $q) => $q->where('manager_id', $user->id)->orWhereHas('team', fn ($tq) => $tq->where('manager_id', $user->id)));

        $totalEvaluated = $resolvedLogs->count();
        $compliantCount = (clone $resolvedLogs)->where('resolution_breached', false)->count();
        $complianceRate = $totalEvaluated > 0 ? (int) round(($compliantCount / $totalEvaluated) * 100) : 100;

        return [
            Stat::make('Managed Projects', $managedProjectsCount)
                ->description('Under your management')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),

            Stat::make('Active Team Tasks', $activeTasks)
                ->description('In progress, review, or pending')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Overdue Tasks', $overdueCount)
                ->description($overdueCount > 0 ? 'Action required by team' : 'Zero overdue tasks')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Team SLA Compliance', "{$complianceRate}%")
                ->description('Supervised projects SLA rating')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($complianceRate >= 90 ? 'success' : 'warning'),
        ];
    }

    protected function getTeamLeaderStats(User $user): array
    {
        $teamTasksQuery = Task::where(function (Builder $q) use ($user) {
            $q->whereHas('project.team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id))
                ->orWhereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id));
        });

        $teamTasksCount = (clone $teamTasksQuery)->count();
        $inProgressCount = (clone $teamTasksQuery)->where('status', 'in_progress')->count();
        $overdueCount = (clone $teamTasksQuery)->active()->whereNotNull('due_at')->where('due_at', '<', now())->count();

        $teamMembersCount = User::whereHas('teams', function (Builder $tq) use ($user) {
            $tq->whereIn('teams.id', $user->ledTeams()->pluck('id'));
        })->count();

        return [
            Stat::make('Team Tasks', $teamTasksCount)
                ->description('Assigned to led team')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('In Progress Work', $inProgressCount)
                ->description('Actively being developed')
                ->descriptionIcon('heroicon-m-play')
                ->color('warning'),

            Stat::make('Overdue Items', $overdueCount)
                ->description($overdueCount > 0 ? 'Past deadline' : 'No overdue items')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Team Members', $teamMembersCount)
                ->description('Active team collaborators')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
        ];
    }

    protected function getMemberStats(User $user): array
    {
        $myTasksQuery = Task::whereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $user->id));

        $myTotalTasks = (clone $myTasksQuery)->count();
        $myInProgress = (clone $myTasksQuery)->where('status', 'in_progress')->count();
        $dueSoon = (clone $myTasksQuery)->active()
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addHours(48))
            ->count();
        $completed = (clone $myTasksQuery)->where('status', 'completed')->count();

        return [
            Stat::make('My Assigned Tasks', $myTotalTasks)
                ->description('Total assigned work items')
                ->descriptionIcon('heroicon-m-clipboard-document')
                ->color('primary'),

            Stat::make('In Progress', $myInProgress)
                ->description('Your current active tasks')
                ->descriptionIcon('heroicon-m-play')
                ->color('warning'),

            Stat::make('Due Soon / Urgent', $dueSoon)
                ->description('Due within next 48 hours')
                ->descriptionIcon('heroicon-m-clock')
                ->color($dueSoon > 0 ? 'danger' : 'success'),

            Stat::make('Completed Tasks', $completed)
                ->description('Successfully finished tasks')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
