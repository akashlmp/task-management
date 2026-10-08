<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProjectAllocation extends Model
{
    use HasFactory;

    protected $table = 'employee_project_allocations';

    protected $fillable = [
        'employee_id',
        'project_id',
        'allocation_percentage',
        'role',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'allocation_percentage' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function scopeActiveProjects(Builder $query): Builder
    {
        return $query->whereHas('project', function (Builder $q) {
            $q->whereNotIn('status', ['completed', 'cancelled']);
        });
    }
}
