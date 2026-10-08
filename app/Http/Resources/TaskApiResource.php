<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name ?? $this->title,
            'description' => $this->description,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'code' => $this->project->code,
                    'name' => $this->project->name,
                ] : null;
            }),
            'assigned_employee_id' => $this->assigned_employee_id,
            'assigned_employee' => $this->whenLoaded('assignedEmployee', function () {
                return $this->assignedEmployee ? [
                    'id' => $this->assignedEmployee->id,
                    'name' => $this->assignedEmployee->name,
                    'email' => $this->assignedEmployee->email,
                    'designation' => $this->assignedEmployee->designation,
                ] : null;
            }),
            'priority' => $this->priority,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->days_overdue,
            'estimated_hours' => (float) $this->estimated_hours,
            'actual_hours' => (float) $this->actual_hours,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
