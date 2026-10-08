<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'client_company',
        'project_manager_id',
        'manager_id',
        'team_id',
        'start_date',
        'deadline',
        'due_date',
        'status',
        'priority',
        'budget',
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'due_date' => 'date',
            'budget' => 'decimal:2',
            'progress' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (blank($project->code)) {
                $project->code = static::generateProjectCode();
            }

            if ($project->project_manager_id && ! $project->manager_id) {
                $project->manager_id = $project->project_manager_id;
            } elseif ($project->manager_id && ! $project->project_manager_id) {
                $project->project_manager_id = $project->manager_id;
            }

            if ($project->deadline && ! $project->due_date) {
                $project->due_date = $project->deadline;
            } elseif ($project->due_date && ! $project->deadline) {
                $project->deadline = $project->due_date;
            }
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('project_manager_id') && ! $project->isDirty('manager_id')) {
                $project->manager_id = $project->project_manager_id;
            } elseif ($project->isDirty('manager_id') && ! $project->isDirty('project_manager_id')) {
                $project->project_manager_id = $project->manager_id;
            }

            if ($project->isDirty('deadline') && ! $project->isDirty('due_date')) {
                $project->due_date = $project->deadline;
            } elseif ($project->isDirty('due_date') && ! $project->isDirty('deadline')) {
                $project->deadline = $project->due_date;
            }
        });
    }

    public static function generateProjectCode(): string
    {
        $maxNumber = 0;
        $codes = static::whereNotNull('code')
            ->where('code', 'like', 'PRJ-%')
            ->pluck('code');

        foreach ($codes as $code) {
            if (preg_match('/^PRJ-(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        do {
            $maxNumber++;
            $candidate = sprintf('PRJ-%03d', $maxNumber);
        } while (static::where('code', $candidate)->exists());

        return $candidate;
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EmployeeProjectAllocation::class, 'project_id');
    }

    public function allocatedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_project_allocations', 'project_id', 'employee_id')
            ->withPivot(['id', 'allocation_percentage', 'role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function employees(): BelongsToMany
    {
        return $this->allocatedEmployees();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function recalculateProgress(): void
    {
        $validTasks = $this->tasks()->where('status', '!=', 'cancelled');
        $count = $validTasks->count();

        if ($count === 0) {
            return;
        }

        $avg = (float) $validTasks->avg('progress');
        $this->updateQuietly([
            'progress' => (int) round($avg),
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('deadline')
            ->where('deadline', '<', now()->startOfDay());
    }
}
