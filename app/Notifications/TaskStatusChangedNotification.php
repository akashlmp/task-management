<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?string $oldStatus,
        public string $newStatus,
        public ?User $changedBy = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $byName = $this->changedBy?->name ?? 'System';
        $from = ucfirst(str_replace('_', ' ', (string) $this->oldStatus));
        $to = ucfirst(str_replace('_', ' ', $this->newStatus));

        return [
            'title' => 'Task Status Updated',
            'body' => "'{$this->task->title}' moved from {$from} to {$to} by {$byName}.",
            'icon' => 'heroicon-o-arrow-path',
            'color' => 'info',
            'task_id' => $this->task->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
