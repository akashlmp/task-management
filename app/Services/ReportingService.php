<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskWorkLog;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportingService
{
    /**
     * Generate aggregate Team Performance metrics.
     */
    public function getTeamPerformance(
        User $user,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $teamId = null,
        ?int $projectId = null
    ): Collection {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : now()->endOfDay();

        $query = Team::query()->with(['manager', 'leader', 'projects']);

        // Scoping by role
        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where('manager_id', $user->id);
            } elseif ($user->hasRole('team_leader')) {
                $query->where('team_leader_id', $user->id);
            } else {
                $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
            }
        }

        if ($teamId) {
            $query->where('id', $teamId);
        }

        $teams = $query->get();

        return $teams->map(function (Team $team) use ($from, $to, $projectId) {
            $tasksQuery = Task::whereHas('project', function (Builder $pq) use ($team) {
                $pq->where('team_id', $team->id);
            })->whereBetween('created_at', [$from, $to]);

            if ($projectId) {
                $tasksQuery->where('project_id', $projectId);
            }

            $tasks = (clone $tasksQuery)->with(['slaLog', 'workLogs'])->get();
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('status', 'completed');
            $completedCount = $completedTasks->count();
            $completionRate = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 0.0;

            // SLA compliance
            $evaluatedSla = $tasks->filter(fn (Task $t) => $t->slaLog && $t->slaLog->resolved_at);
            $slaBreached = $evaluatedSla->filter(fn (Task $t) => $t->slaLog->resolution_breached || $t->slaLog->response_breached)->count();
            $slaCompliant = $evaluatedSla->count() - $slaBreached;
            $slaComplianceRate = $evaluatedSla->count() > 0
                ? round(($slaCompliant / $evaluatedSla->count()) * 100, 1)
                : 100.0;

            // Average resolution time in hours
            $resolutionHoursSum = 0;
            $resolutionCount = 0;
            foreach ($completedTasks as $task) {
                if ($task->slaLog && $task->slaLog->resolved_at) {
                    $resolutionHoursSum += $task->created_at->diffInMinutes($task->slaLog->resolved_at) / 60;
                    $resolutionCount++;
                }
            }
            $avgResolutionHours = $resolutionCount > 0 ? round($resolutionHoursSum / $resolutionCount, 1) : 0.0;

            // Total work hours logged
            $totalLoggedMinutes = TaskWorkLog::whereIn('task_id', $tasks->pluck('id'))->sum('duration_minutes');
            $totalLoggedHours = round($totalLoggedMinutes / 60, 1);

            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'manager_name' => $team->manager?->name ?? 'Unassigned',
                'leader_name' => $team->leader?->name ?? 'Unassigned',
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedCount,
                'completion_rate' => $completionRate,
                'sla_compliant' => $slaCompliant,
                'sla_breached' => $slaBreached,
                'sla_compliance_rate' => $slaComplianceRate,
                'avg_resolution_hours' => $avgResolutionHours,
                'total_logged_hours' => $totalLoggedHours,
            ];
        });
    }

    /**
     * Generate aggregate Member Performance metrics.
     */
    public function getMemberPerformance(
        User $user,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $teamId = null
    ): Collection {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : now()->endOfDay();

        $query = User::query()->where('status', 'active')->with(['teams']);

        // Scoping by role
        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->whereHas('teams', fn (Builder $tq) => $tq->where('manager_id', $user->id));
            } elseif ($user->hasRole('team_leader')) {
                $query->whereHas('teams', fn (Builder $tq) => $tq->where('team_leader_id', $user->id));
            } else {
                $query->where('id', $user->id);
            }
        }

        if ($teamId) {
            $query->whereHas('teams', fn (Builder $tq) => $tq->where('teams.id', $teamId));
        }

        $users = $query->get();

        return $users->map(function (User $member) use ($from, $to) {
            $assignedTasks = Task::whereHas('assignees', fn (Builder $aq) => $aq->where('users.id', $member->id))
                ->whereBetween('created_at', [$from, $to])
                ->with('slaLog')
                ->get();

            $totalAssigned = $assignedTasks->count();
            $completedTasks = $assignedTasks->where('status', 'completed');
            $completedCount = $completedTasks->count();
            $completionRate = $totalAssigned > 0 ? round(($completedCount / $totalAssigned) * 100, 1) : 0.0;

            // On-time completion: completed tasks where due_at is null or completed before due_at
            $onTimeCount = $completedTasks->filter(function (Task $t) {
                if (! $t->due_at) {
                    return true;
                }
                $completedAt = $t->slaLog?->resolved_at ?? $t->updated_at;
                return $completedAt <= $t->due_at;
            })->count();

            $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100, 1) : 100.0;

            // SLA breaches on tasks assigned to this member
            $slaBreaches = $assignedTasks->filter(function (Task $t) {
                return $t->slaLog && ($t->slaLog->response_breached || $t->slaLog->resolution_breached);
            })->count();

            // Work hours logged by this member in period
            $loggedMinutes = TaskWorkLog::where('user_id', $member->id)
                ->whereBetween('created_at', [$from, $to])
                ->sum('duration_minutes');
            $loggedHours = round($loggedMinutes / 60, 1);

            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'employee_code' => $member->employee_code ?? '-',
                'email' => $member->email,
                'team_name' => $member->teams->first()?->name ?? 'Unassigned',
                'total_assigned' => $totalAssigned,
                'completed_tasks' => $completedCount,
                'completion_rate' => $completionRate,
                'on_time_rate' => $onTimeRate,
                'sla_breaches' => $slaBreaches,
                'logged_hours' => $loggedHours,
            ];
        });
    }

    /**
     * Generate aggregate Project SLA & Health metrics.
     */
    public function getProjectHealth(
        User $user,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $teamId = null,
        ?int $projectId = null
    ): Collection {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : now()->endOfDay();

        $query = Project::query()->with(['team', 'manager']);

        // Scoping by role
        if (! $user->hasRole('admin')) {
            if ($user->hasRole('manager')) {
                $query->where(function (Builder $q) use ($user) {
                    $q->where('manager_id', $user->id)
                        ->orWhereHas('team', fn (Builder $tq) => $tq->where('manager_id', $user->id));
                });
            } elseif ($user->hasRole('team_leader')) {
                $query->whereHas('team', fn (Builder $tq) => $tq->where('team_leader_id', $user->id));
            } else {
                $query->whereHas('members', fn (Builder $mq) => $mq->where('users.id', $user->id));
            }
        }

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($projectId) {
            $query->where('id', $projectId);
        }

        $projects = $query->get();

        return $projects->map(function (Project $project) use ($from, $to) {
            $tasks = Task::where('project_id', $project->id)
                ->whereBetween('created_at', [$from, $to])
                ->with('slaLog')
                ->get();

            $totalTasks = $tasks->count();
            $completedCount = $tasks->where('status', 'completed')->count();
            $inProgressCount = $tasks->where('status', 'in_progress')->count();
            $overdueCount = $tasks->filter(fn (Task $t) => ! in_array($t->status, ['completed', 'cancelled']) && $t->due_at && $t->due_at < now())->count();
            $progressPercent = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 0.0;

            // SLA Compliance
            $evaluatedSla = $tasks->filter(fn (Task $t) => $t->slaLog && $t->slaLog->resolved_at);
            $slaBreached = $evaluatedSla->filter(fn (Task $t) => $t->slaLog->resolution_breached || $t->slaLog->response_breached)->count();
            $slaCompliant = $evaluatedSla->count() - $slaBreached;
            $slaComplianceRate = $evaluatedSla->count() > 0
                ? round(($slaCompliant / $evaluatedSla->count()) * 100, 1)
                : 100.0;

            return [
                'project_id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'team_name' => $project->team?->name ?? 'General',
                'manager_name' => $project->manager?->name ?? 'Unassigned',
                'status' => $project->status,
                'priority' => $project->priority,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedCount,
                'in_progress_tasks' => $inProgressCount,
                'overdue_tasks' => $overdueCount,
                'progress_percent' => $progressPercent,
                'sla_compliance_rate' => $slaComplianceRate,
            ];
        });
    }

    /**
     * Convert collection data into CSV string for download.
     */
    public function generateCsv(array $headers, Collection $rows): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
