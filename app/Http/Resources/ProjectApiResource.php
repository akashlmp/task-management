<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectApiResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'client_company' => $this->client_company,
            'status' => $this->status,
            'priority' => $this->priority,
            'budget' => (float) $this->budget,
            'progress' => (int) $this->progress,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'deadline' => $this->deadline?->format('Y-m-d') ?? $this->due_date?->format('Y-m-d'),
            'is_overdue' => $this->deadline && $this->deadline->isPast() && ! $this->isClosed(),
            'project_manager' => $this->whenLoaded('projectManager', function () {
                return $this->projectManager ? [
                    'id' => $this->projectManager->id,
                    'name' => $this->projectManager->name,
                    'email' => $this->projectManager->email,
                ] : null;
            }),
            'tasks_count' => $this->whenCounted('tasks'),
            'allocations_count' => $this->whenCounted('allocations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
