<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?User $completedBy = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $byName = $this->completedBy?->name ?? 'A team member';

        return [
            'title' => 'Task Completed',
            'body' => "Task '{$this->task->title}' was marked as completed by {$byName}.",
            'icon' => 'heroicon-o-check-badge',
            'color' => 'success',
            'task_id' => $this->task->id,
            'project_name' => $this->task->project?->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
