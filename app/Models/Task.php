<?php

namespace App\Models;

use App\Services\SlaService;
use Carbon\Carbon;
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
        'name',
        'title',
        'code',
        'description',
        'assigned_employee_id',
        'created_by',
        'priority',
        'status',
        'start_date',
        'start_at',
        'due_date',
        'due_at',
        'completed_at',
        'progress',
        'estimated_hours',
        'actual_hours',
        'estimated_minutes',
        'actual_minutes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'actual_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            // Auto code generation
            if (blank($task->code)) {
                $task->code = static::generateTaskCode();
            }

            // Sync name and title
            if ($task->name && ! $task->title) {
                $task->title = $task->name;
            } elseif ($task->title && ! $task->name) {
                $task->name = $task->title;
            }

            // Sync dates
            if ($task->due_date && ! $task->due_at) {
                $task->due_at = Carbon::parse($task->due_date)->endOfDay();
            } elseif ($task->due_at && ! $task->due_date) {
                $task->due_date = Carbon::parse($task->due_at)->toDateString();
            }

            if ($task->start_date && ! $task->start_at) {
                $task->start_at = Carbon::parse($task->start_date)->startOfDay();
            } elseif ($task->start_at && ! $task->start_date) {
                $task->start_date = Carbon::parse($task->start_at)->toDateString();
            }

            // Sync hours & minutes
            if ($task->estimated_hours && ! $task->estimated_minutes) {
                $task->estimated_minutes = (int) round($task->estimated_hours * 60);
            } elseif ($task->estimated_minutes && ! $task->estimated_hours) {
                $task->estimated_hours = round($task->estimated_minutes / 60, 2);
            }

            if (! $task->created_by && auth()->check()) {
                $task->created_by = auth()->id();
            }

            // Auto progress sync on create
            if ($task->status === 'completed' && $task->progress < 100) {
                $task->progress = 100;
                $task->completed_at = now();
            } elseif ($task->progress >= 100 && $task->status !== 'completed') {
                $task->status = 'completed';
                $task->completed_at = now();
            }

            // Automatically link matching active SLA policy if not explicitly set
            if (! $task->sla_policy_id && $task->priority) {
                $policy = SlaPolicy::active()->forPriority($task->priority)->first();
                if ($policy) {
                    $task->sla_policy_id = $policy->id;
                    if (! $task->due_at) {
                        $task->due_at = now()->addMinutes($policy->resolution_time_minutes);
                        $task->due_date = $task->due_at->toDateString();
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
                'comment' => 'Task created with status ' . ucfirst(str_replace('_', ' ', (string) $task->status)),
                'started_at' => now(),
            ]);

            try {
                if (class_exists(SlaService::class)) {
                    app(SlaService::class)->initializeTaskSla($task);
                }
            } catch (\Throwable $e) {
                // Sla log initialization optional if service or policy absent
            }
        });

        static::updating(function (Task $task) {
            // Sync name and title
            if ($task->isDirty('name') && ! $task->isDirty('title')) {
                $task->title = $task->name;
            } elseif ($task->isDirty('title') && ! $task->isDirty('name')) {
                $task->name = $task->title;
            }

            // Sync dates
            if ($task->isDirty('due_date') && ! $task->isDirty('due_at')) {
                $task->due_at = $task->due_date ? Carbon::parse($task->due_date)->endOfDay() : null;
            } elseif ($task->isDirty('due_at') && ! $task->isDirty('due_date')) {
                $task->due_date = $task->due_at ? Carbon::parse($task->due_at)->toDateString() : null;
            }

            if ($task->isDirty('start_date') && ! $task->isDirty('start_at')) {
                $task->start_at = $task->start_date ? Carbon::parse($task->start_date)->startOfDay() : null;
            } elseif ($task->isDirty('start_at') && ! $task->isDirty('start_date')) {
                $task->start_date = $task->start_at ? Carbon::parse($task->start_at)->toDateString() : null;
            }

            // Sync hours & minutes
            if ($task->isDirty('estimated_hours')) {
                $task->estimated_minutes = (int) round($task->estimated_hours * 60);
            }
            if ($task->isDirty('actual_hours')) {
                $task->actual_minutes = (int) round($task->actual_hours * 60);
            }

            // Auto-Progress Sync rules:
            // 1. If task status changed to completed, set progress = 100
            if ($task->isDirty('status') && $task->status === 'completed' && $task->progress !== 100) {
                $task->progress = 100;
                if (! $task->completed_at) {
                    $task->completed_at = now();
                }
            }
            // 2. If progress set to 100, set status = completed
            elseif ($task->isDirty('progress') && (int) $task->progress === 100 && $task->status !== 'completed') {
                $task->status = 'completed';
                if (! $task->completed_at) {
                    $task->completed_at = now();
                }
            }
            // 3. If status changed away from completed, clear completed_at
            elseif ($task->isDirty('status') && $task->getOriginal('status') === 'completed' && $task->status !== 'completed') {
                $task->completed_at = null;
                if ($task->progress === 100) {
                    $task->progress = 50;
                }
            }

            if ($task->isDirty('status')) {
                $oldStatus = $task->getOriginal('status');
                $newStatus = $task->status;

                TaskStatusHistory::create([
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'comment' => 'Status changed from ' . ucfirst(str_replace('_', ' ', (string) $oldStatus)) . ' to ' . ucfirst(str_replace('_', ' ', (string) $newStatus)),
                    'started_at' => now(),
                ]);

                if ($newStatus === 'in_progress') {
                    if (! $task->start_at) {
                        $task->start_at = now();
                        $task->start_date = now()->toDateString();
                    }
                    try {
                        if (class_exists(SlaService::class)) {
                            app(SlaService::class)->recordTaskResponse($task);
                        }
                    } catch (\Throwable $e) {}
                }

                if ($newStatus === 'completed') {
                    if (! $task->completed_at) {
                        $task->completed_at = now();
                    }
                    try {
                        if (class_exists(SlaService::class)) {
                            app(SlaService::class)->recordTaskResolution($task);
                        }
                    } catch (\Throwable $e) {}
                }
            }
        });
    }

    public static function generateTaskCode(): string
    {
        $maxNumber = 0;
        $codes = static::whereNotNull('code')
            ->where('code', 'like', 'TSK-%')
            ->pluck('code');

        foreach ($codes as $code) {
            if (preg_match('/^TSK-(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        do {
            $maxNumber++;
            $candidate = sprintf('TSK-%03d', $maxNumber);
        } while (static::where('code', $candidate)->exists());

        return $candidate;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->assignedEmployee();
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at', 'accepted_at', 'completed_at', 'is_primary'])
            ->withTimestamps();
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
        $this->updateQuietly([
            'actual_minutes' => $total,
            'actual_hours' => round($total / 60, 2),
        ]);
    }

    public function isOverdue(): bool
    {
        if (in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }

        $due = $this->due_date ? Carbon::parse($this->due_date)->endOfDay() : $this->due_at;

        return $due && $due->isPast();
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (! $this->isOverdue()) {
            return null;
        }

        $due = $this->due_date ? Carbon::parse($this->due_date)->endOfDay() : $this->due_at;

        return (int) $due->diffInDays(now(), false);
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
        return $query->active()->where(function (Builder $q) {
            $q->where(function (Builder $sub) {
                $sub->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
            })->orWhere(function (Builder $sub) {
                $sub->whereNotNull('due_at')->where('due_at', '<', now());
            });
        });
    }
}
