<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SlaWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $type = 'resolution', // 'response' or 'resolution'
        public ?int $minutesRemaining = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabel = $this->type === 'response' ? 'Initial Response' : 'Resolution';
        $remainingText = $this->minutesRemaining !== null ? " (~{$this->minutesRemaining} minutes remaining)" : '';

        return [
            'title' => "SLA Warning: {$typeLabel} Deadline Approaching",
            'body' => "Task '{$this->task->title}' is approaching its {$typeLabel} SLA limit{$remainingText}.",
            'icon' => 'heroicon-o-exclamation-triangle',
            'color' => 'warning',
            'task_id' => $this->task->id,
            'type' => $this->type,
            'priority' => $this->task->priority,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
