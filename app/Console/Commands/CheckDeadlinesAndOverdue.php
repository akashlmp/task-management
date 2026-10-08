<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CheckDeadlinesAndOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check projects and tasks approaching deadline (within 48 hours) or overdue, and send database notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $in48Hours = now()->addHours(48);

        // 1. Projects reaching deadline within 48 hours
        $upcomingProjects = Project::active()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$now->toDateString(), $in48Hours->toDateString()])
            ->get();

        foreach ($upcomingProjects as $project) {
            if ($project->projectManager) {
                Notification::make()
                    ->title('Project Deadline Approaching')
                    ->body("Project '{$project->name}' ({$project->code}) is due within 48 hours (Deadline: {$project->deadline->format('M d, Y')}).")
                    ->warning()
                    ->icon('heroicon-o-clock')
                    ->sendToDatabase($project->projectManager);
            }
        }

        // 2. Overdue Projects
        $overdueProjects = Project::active()
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now->toDateString())
            ->get();

        foreach ($overdueProjects as $project) {
            if ($project->projectManager) {
                Notification::make()
                    ->title('Project Overdue Alert')
                    ->body("Project '{$project->name}' ({$project->code}) is past its deadline ({$project->deadline->format('M d, Y')}).")
                    ->danger()
                    ->icon('heroicon-o-exclamation-circle')
                    ->sendToDatabase($project->projectManager);
            }
        }

        // 3. Tasks reaching deadline within 48 hours
        $upcomingTasks = Task::active()
            ->where(function ($q) use ($now, $in48Hours) {
                $q->whereBetween('due_date', [$now->toDateString(), $in48Hours->toDateString()])
                    ->orWhereBetween('due_at', [$now, $in48Hours]);
            })
            ->get();

        foreach ($upcomingTasks as $task) {
            if ($task->assignedEmployee) {
                $dueStr = $task->due_date ? $task->due_date->format('M d, Y') : $task->due_at?->format('M d, Y H:i');
                Notification::make()
                    ->title('Task Due in 48 Hours')
                    ->body("Task '{$task->name}' ({$task->code}) on '{$task->project?->name}' is due on {$dueStr}.")
                    ->warning()
                    ->icon('heroicon-o-clock')
                    ->sendToDatabase($task->assignedEmployee);
            }
        }

        // 4. Overdue Tasks
        $overdueTasks = Task::overdue()->get();
        foreach ($overdueTasks as $task) {
            if ($task->assignedEmployee) {
                Notification::make()
                    ->title('Task Overdue')
                    ->body("Task '{$task->name}' ({$task->code}) is overdue!")
                    ->danger()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->sendToDatabase($task->assignedEmployee);
            }
        }

        $this->info("Checked deadlines: {$upcomingProjects->count()} upcoming projects, {$overdueProjects->count()} overdue projects, {$upcomingTasks->count()} upcoming tasks, {$overdueTasks->count()} overdue tasks.");

        return Command::SUCCESS;
    }
}
