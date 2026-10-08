<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationApiResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', function () {
                return $this->employee ? [
                    'id' => $this->employee->id,
                    'name' => $this->employee->name,
                    'email' => $this->employee->email,
                    'designation' => $this->employee->designation,
                    'active_workload_percentage' => $this->employee->active_allocation_percentage,
                ] : null;
            }),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'code' => $this->project->code,
                    'name' => $this->project->name,
                    'status' => $this->project->status,
                ] : null;
            }),
            'allocation_percentage' => (int) $this->allocation_percentage,
            'role' => $this->role,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
