<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_code',
        'phone',
        'status',
        'joined_at',
        'joining_date',
        'department_id',
        'designation',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_at' => 'date',
            'joining_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->employee_code)) {
                $user->employee_code = static::generateEmployeeCode();
            }

            if ($user->joining_date && ! $user->joined_at) {
                $user->joined_at = $user->joining_date;
            } elseif ($user->joined_at && ! $user->joining_date) {
                $user->joining_date = $user->joined_at;
            }
        });

        static::updating(function (User $user) {
            if ($user->isDirty('joining_date') && ! $user->isDirty('joined_at')) {
                $user->joined_at = $user->joining_date;
            } elseif ($user->isDirty('joined_at') && ! $user->isDirty('joining_date')) {
                $user->joining_date = $user->joined_at;
            }
        });
    }

    public static function generateEmployeeCode(): string
    {
        $maxNumber = 0;

        $codes = static::whereNotNull('employee_code')
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('employee_code');

        foreach ($codes as $code) {
            if (preg_match('/^EMP-(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        do {
            $maxNumber++;
            $candidate = sprintf('EMP-%03d', $maxNumber);
        } while (static::where('employee_code', $candidate)->exists());

        return $candidate;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EmployeeProjectAllocation::class, 'employee_id');
    }

    public function allocatedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'employee_project_allocations', 'employee_id', 'project_id')
            ->withPivot(['id', 'allocation_percentage', 'role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_employee_id');
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'department_head_id');
    }

    public function getActiveAllocationPercentageAttribute(): int
    {
        return (int) $this->allocations()
            ->whereHas('project', fn (Builder $q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->sum('allocation_percentage');
    }

    public function getRemainingAllocationPercentageAttribute(): int
    {
        return max(0, 100 - $this->active_allocation_percentage);
    }

    // Existing relationship compatibility
    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_leader_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot(['joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->assignedTasks();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(TaskWorkLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
