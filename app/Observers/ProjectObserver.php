<?php

namespace App\Observers;

use App\Models\Project;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ProjectObserver
{
    /**
     * Handle the Project "saving" event.
     */
    public function saving(Project $project): void
    {
        // 1. Date Validation
        $start = $project->start_date;
        $deadline = $project->deadline ?? $project->due_date;

        if ($start && $deadline) {
            if ($deadline < $start) {
                throw ValidationException::withMessages([
                    'deadline' => 'Project deadline cannot precede start date.',
                ]);
            }
        }
    }

    /**
     * Handle the Project "saved" event.
     */
    public function saved(Project $project): void
    {
        // Notify Project Manager on assignment
        if ($project->wasChanged('project_manager_id') && $project->project_manager_id) {
            $manager = $project->projectManager;
            if ($manager) {
                Notification::make()
                    ->title('Project Manager Assignment')
                    ->body("You have been assigned as Project Manager for '{$project->name}' ({$project->code}).")
                    ->icon('heroicon-o-briefcase')
                    ->success()
                    ->sendToDatabase($manager);
            }
        }
    }
}
