<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?User $assignedBy = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $byName = $this->assignedBy?->name ?? 'System';

        return [
            'title' => 'New Task Assigned',
            'body' => "You have been assigned to '{$this->task->title}' by {$byName}.",
            'icon' => 'heroicon-o-clipboard-document-check',
            'color' => 'primary',
            'task_id' => $this->task->id,
            'project_name' => $this->task->project?->name,
            'priority' => $this->task->priority,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
