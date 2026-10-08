<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectApiResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Project::with('projectManager')->withCount(['tasks', 'allocations']);

        // RBAC: Employees can only view assigned projects
        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhereHas('allocations', fn (Builder $aq) => $aq->where('employee_id', $user->id));
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        return ProjectApiResource::collection($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:projects,code',
            'client_company' => 'nullable|string|max:255',
            'project_manager_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:planning,in_progress,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $project = Project::create($validated);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => new ProjectApiResource($project->load('projectManager')),
        ], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // RBAC Check
        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            $isAllocated = $project->allocations()->where('employee_id', $user->id)->exists()
                || $project->project_manager_id === $user->id;

            if (! $isAllocated) {
                return response()->json(['message' => 'Unauthorized to view this project.'], 403);
            }
        }

        return response()->json([
            'data' => new ProjectApiResource($project->load(['projectManager', 'tasks', 'allocations.employee'])),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50|unique:projects,code,' . $project->id,
            'client_company' => 'nullable|string|max:255',
            'project_manager_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|required|in:planning,in_progress,on_hold,completed,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'progress' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => new ProjectApiResource($project->load('projectManager')),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully.',
        ], 200);
    }
}
