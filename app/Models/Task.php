<?php

namespace App\Models;

use App\Services\SlaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_task_id',
        'sla_policy_id',
        'title',
        'description',
        'created_by',
        'priority',
        'status',
        'start_at',
        'due_at',
        'completed_at',
        'estimated_minutes',
        'actual_minutes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_minutes' => 'integer',
            'actual_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (! $task->created_by && auth()->check()) {
                $task->created_by = auth()->id();
            }

            // Automatically link matching active SLA policy if not explicitly set
            if (! $task->sla_policy_id && $task->priority) {
                $policy = SlaPolicy::active()->forPriority($task->priority)->first();
                if ($policy) {
                    $task->sla_policy_id = $policy->id;
                    if (! $task->due_at) {
                        $task->due_at = now()->addMinutes($policy->resolution_time_minutes);
                    }
                }
            }
        });

        static::created(function (Task $task) {
            TaskStatusHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id() ?? $task->created_by,
                'old_status' => null,
                'new_status' => $task->status,
                'comment' => 'Task created with status ' . ucfirst(str_replace('_', ' ', $task->status)),
                'started_at' => now(),
            ]);

            app(SlaService::class)->initializeTaskSla($task);
        });

        static::updating(function (Task $task) {
            if ($task->isDirty('status')) {
                $oldStatus = $task->getOriginal('status');
                $newStatus = $task->status;

                TaskStatusHistory::create([
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'comment' => 'Status changed from ' . ucfirst(str_replace('_', ' ', (string) $oldStatus)) . ' to ' . ucfirst(str_replace('_', ' ', $newStatus)),
                    'started_at' => now(),
                ]);

                if ($newStatus === 'in_progress') {
                    if (! $task->start_at) {
                        $task->start_at = now();
                    }
                    app(SlaService::class)->recordTaskResponse($task);
                }

                if ($newStatus === 'completed') {
                    if (! $task->completed_at) {
                        $task->completed_at = now();
                    }
                    app(SlaService::class)->recordTaskResolution($task);
                }
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function slaLog(): HasOne
    {
        return $this->hasOne(TaskSlaLog::class);
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at', 'accepted_at', 'completed_at', 'is_primary'])
            ->withTimestamps();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->orderByDesc('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderByDesc('created_at');
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(TaskWorkLog::class)->orderByDesc('started_at');
    }

    public function recalculateActualMinutes(): void
    {
        $total = (int) $this->workLogs()->sum('duration_minutes');
        $this->updateQuietly(['actual_minutes' => $total]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
