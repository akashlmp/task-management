<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Task;
use App\Models\TaskSlaLog;
use App\Models\User;
use App\Notifications\SlaBreachedNotification;
use App\Notifications\SlaWarningNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SlaService
{
    /**
     * Initialize SLA Log when task is created.
     */
    public function initializeTaskSla(Task $task): ?TaskSlaLog
    {
        if (! $task->sla_policy_id) {
            $policy = SlaPolicy::active()->forPriority($task->priority)->first();
            if ($policy) {
                $task->sla_policy_id = $policy->id;
                $task->saveQuietly();
            } else {
                return null;
            }
        } else {
            $policy = $task->slaPolicy ?? SlaPolicy::find($task->sla_policy_id);
        }

        if (! $policy) {
            return null;
        }

        $createdAt = $task->created_at ?? now();

        return TaskSlaLog::firstOrCreate(
            ['task_id' => $task->id],
            [
                'sla_policy_id' => $policy->id,
                'response_due_at' => (clone $createdAt)->addMinutes($policy->response_time_minutes),
                'resolution_due_at' => (clone $createdAt)->addMinutes($policy->resolution_time_minutes),
                'response_breached' => false,
                'resolution_breached' => false,
                'response_breach_minutes' => 0,
                'resolution_breach_minutes' => 0,
            ]
        );
    }

    /**
     * Record when team begins work on a task.
     */
    public function recordTaskResponse(Task $task): void
    {
        $log = $task->slaLog ?? $this->initializeTaskSla($task);

        if (! $log || $log->response_at) {
            return;
        }

        $now = now();
        $log->response_at = $now;

        if ($log->response_due_at && $now->greaterThan($log->response_due_at)) {
            $log->response_breached = true;
            $log->response_breach_minutes = abs((int) $now->diffInMinutes($log->response_due_at));
        }

        $log->saveQuietly();
    }

    /**
     * Record when task is completed.
     */
    public function recordTaskResolution(Task $task): void
    {
        $log = $task->slaLog ?? $this->initializeTaskSla($task);

        if (! $log || $log->resolved_at) {
            return;
        }

        $now = now();
        $log->resolved_at = $now;

        if ($log->resolution_due_at && $now->greaterThan($log->resolution_due_at)) {
            $log->resolution_breached = true;
            $log->resolution_breach_minutes = abs((int) $now->diffInMinutes($log->resolution_due_at));
        }

        $log->saveQuietly();
    }

    /**
     * Check for SLA warnings and breaches across all active tasks.
     * Idempotent: only notifies once per stage.
     */
    public function checkSlaBreachesAndWarnings(): array
    {
        $activeTasks = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['slaPolicy', 'slaLog', 'assignees', 'project.manager', 'project.team.manager'])
            ->get();

        $warningsCount = 0;
        $breachesCount = 0;
        $now = now();

        foreach ($activeTasks as $task) {
            $log = $task->slaLog;
            if (! $log) {
                $log = $this->initializeTaskSla($task);
            }

            $policy = $task->slaPolicy ?? $log?->slaPolicy;
            if (! $log || ! $policy) {
                continue;
            }

            $recipients = $this->getNotificationRecipients($task);

            // 1. Response Warning
            if (! $log->response_at && ! $log->response_breached && ! $log->response_warning_sent_at) {
                $threshold = (clone $task->created_at)->addMinutes($policy->response_warning_minutes);
                if ($now->greaterThanOrEqualTo($threshold)) {
                    $minutesRemaining = max(0, (int) $now->diffInMinutes($log->response_due_at, false));
                    Notification::send($recipients, new SlaWarningNotification($task, 'response', $minutesRemaining));
                    $log->response_warning_sent_at = $now;
                    $log->saveQuietly();
                    $warningsCount++;
                }
            }

            // 2. Response Breach
            if (! $log->response_at && ! $log->response_breach_sent_at && $log->response_due_at && $now->greaterThan($log->response_due_at)) {
                $log->response_breached = true;
                $log->response_breach_minutes = abs((int) $now->diffInMinutes($log->response_due_at));
                Notification::send($recipients, new SlaBreachedNotification($task, 'response', $log->response_breach_minutes));
                $log->response_breach_sent_at = $now;
                $log->saveQuietly();
                $breachesCount++;
            }

            // 3. Resolution Warning
            if (! $log->resolved_at && ! $log->resolution_breached && ! $log->resolution_warning_sent_at) {
                $threshold = (clone $task->created_at)->addMinutes($policy->resolution_warning_minutes);
                if ($now->greaterThanOrEqualTo($threshold)) {
                    $minutesRemaining = max(0, abs((int) $now->diffInMinutes($log->resolution_due_at)));
                    Notification::send($recipients, new SlaWarningNotification($task, 'resolution', $minutesRemaining));
                    $log->resolution_warning_sent_at = $now;
                    $log->saveQuietly();
                    $warningsCount++;
                }
            }

            // 4. Resolution Breach
            if (! $log->resolved_at && ! $log->resolution_breach_sent_at && $log->resolution_due_at && $now->greaterThan($log->resolution_due_at)) {
                $log->resolution_breached = true;
                $log->resolution_breach_minutes = abs((int) $now->diffInMinutes($log->resolution_due_at));
                Notification::send($recipients, new SlaBreachedNotification($task, 'resolution', $log->resolution_breach_minutes));
                $log->resolution_breach_sent_at = $now;
                $log->saveQuietly();
                $breachesCount++;
            }
        }

        return [
            'warnings_sent' => $warningsCount,
            'breaches_sent' => $breachesCount,
        ];
    }

    /**
     * Gather assignees and project managers for notification delivery.
     */
    protected function getNotificationRecipients(Task $task): Collection
    {
        $recipients = collect();

        foreach ($task->assignees as $assignee) {
            $recipients->push($assignee);
        }

        if ($task->project?->manager) {
            $recipients->push($task->project->manager);
        }

        if ($task->project?->team?->manager) {
            $recipients->push($task->project->team->manager);
        }

        return $recipients->unique('id');
    }
}
