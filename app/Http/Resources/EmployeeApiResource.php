<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeApiResource extends JsonResource
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
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ] : null;
            }),
            'status' => $this->status,
            'joining_date' => $this->joining_date?->format('Y-m-d') ?? $this->joined_at?->format('Y-m-d'),
            'profile_image' => $this->profile_image ? url('storage/' . $this->profile_image) : null,
            'active_workload_percentage' => $this->active_allocation_percentage,
            'remaining_capacity_percentage' => $this->remaining_allocation_percentage,
            'roles' => $this->roles->pluck('name'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
