<?php

namespace App\Observers;

use App\Models\Task;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class TaskObserver
{
    /**
     * Handle the Task "creating" event.
     */
    public function creating(Task $task): void
    {
        // 1. Closed Project Guard
        if ($task->project_id) {
            $project = $task->project()->first();
            if ($project && $project->isClosed()) {
                throw ValidationException::withMessages([
                    'project_id' => "Cannot add new tasks to a {$project->status} project ({$project->name}).",
                ]);
            }
        }

        // 2. Date Validation
        if ($task->start_date && $task->due_date) {
            if ($task->due_date < $task->start_date) {
                throw ValidationException::withMessages([
                    'due_date' => 'Task due date cannot precede start date.',
                ]);
            }
        }
    }

    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Auto-Progress Sync: recalculate project progress
        $task->project?->recalculateProgress();

        // Notification: Task assignment
        if ($task->assigned_employee_id) {
            $employee = $task->assignedEmployee;
            if ($employee) {
                Notification::make()
                    ->title('New Task Assigned')
                    ->body("You have been assigned to task '{$task->name}' ({$task->code}) on project '{$task->project?->name}'.")
                    ->icon('heroicon-o-clipboard-document-check')
                    ->success()
                    ->sendToDatabase($employee);
            }
        }
    }

    /**
     * Handle the Task "updating" event.
     */
    public function updating(Task $task): void
    {
        // Date Validation
        if ($task->start_date && $task->due_date) {
            if ($task->due_date < $task->start_date) {
                throw ValidationException::withMessages([
                    'due_date' => 'Task due date cannot precede start date.',
                ]);
            }
        }
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Auto-Progress Sync: recalculate project progress
        $task->project?->recalculateProgress();

        // Notification: Assignee change
        if ($task->wasChanged('assigned_employee_id') && $task->assigned_employee_id) {
            $employee = $task->assignedEmployee;
            if ($employee) {
                Notification::make()
                    ->title('Task Reassigned')
                    ->body("You have been assigned to task '{$task->name}' ({$task->code}) on project '{$task->project?->name}'.")
                    ->icon('heroicon-o-clipboard-document-check')
                    ->info()
                    ->sendToDatabase($employee);
            }
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $task->project?->recalculateProgress();
    }
}
