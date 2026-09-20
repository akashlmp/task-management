<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSlaLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'sla_policy_id',
        'response_due_at',
        'resolution_due_at',
        'response_at',
        'resolved_at',
        'response_breached',
        'resolution_breached',
        'response_breach_minutes',
        'resolution_breach_minutes',
        'response_warning_sent_at',
        'response_breach_sent_at',
        'resolution_warning_sent_at',
        'resolution_breach_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'response_breached' => 'boolean',
            'resolution_breached' => 'boolean',
            'response_breach_minutes' => 'integer',
            'resolution_breach_minutes' => 'integer',
            'response_warning_sent_at' => 'datetime',
            'response_breach_sent_at' => 'datetime',
            'resolution_warning_sent_at' => 'datetime',
            'resolution_breach_sent_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function isResponseOverdue(): bool
    {
        if ($this->response_at) {
            return $this->response_breached;
        }

        return $this->response_due_at && now()->greaterThan($this->response_due_at);
    }

    public function isResolutionOverdue(): bool
    {
        if ($this->resolved_at) {
            return $this->resolution_breached;
        }

        return $this->resolution_due_at && now()->greaterThan($this->resolution_due_at);
    }
}
