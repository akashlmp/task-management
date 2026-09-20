<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SlaBreachedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $type = 'resolution', // 'response' or 'resolution'
        public ?int $breachMinutes = 0
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabel = $this->type === 'response' ? 'Initial Response' : 'Resolution';
        $overdueText = $this->breachMinutes > 0 ? " by {$this->breachMinutes} minutes" : '';

        return [
            'title' => "SLA Breach Alert: {$typeLabel} Overdue",
            'body' => "Task '{$this->task->title}' has breached its {$typeLabel} SLA deadline{$overdueText}.",
            'icon' => 'heroicon-o-x-circle',
            'color' => 'danger',
            'task_id' => $this->task->id,
            'type' => $this->type,
            'priority' => $this->task->priority,
            'breach_minutes' => $this->breachMinutes,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
