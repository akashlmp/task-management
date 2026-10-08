<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskApiResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Task::with(['project', 'assignedEmployee']);

        // RBAC: Employees can only view their own assigned tasks
        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            $query->where('assigned_employee_id', $user->id);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        return TaskApiResource::collection($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only managers or admins can create tasks
        if ($user && ! $user->hasRole(['admin', 'manager']) && ! $user->can('tasks.create')) {
            return response()->json(['message' => 'Unauthorized to create tasks.'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:tasks,code',
            'assigned_employee_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,on_hold,completed,cancelled',
            'progress' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        // Closed project check
        $project = Project::find($validated['project_id']);
        if ($project && $project->isClosed()) {
            return response()->json([
                'message' => "Cannot create tasks for a {$project->status} project.",
            ], 422);
        }

        $task = Task::create($validated);

        return response()->json([
            'message' => 'Task created successfully.',
            'data' => new TaskApiResource($task->load(['project', 'assignedEmployee'])),
        ], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        // RBAC: Employee check
        if ($user && ! $user->hasRole(['admin', 'manager'])) {
            if ($task->assigned_employee_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to view this task.'], 403);
            }
        }

        return response()->json([
            'data' => new TaskApiResource($task->load(['project', 'assignedEmployee'])),
        ]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $isManager = $user && $user->hasRole(['admin', 'manager']);

        // RBAC rule: Employees can only update task progress and status on their own tasks
        if (! $isManager) {
            if ($task->assigned_employee_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to update this task.'], 403);
            }

            $validated = $request->validate([
                'status' => 'sometimes|required|in:pending,in_progress,on_hold,completed,cancelled',
                'progress' => 'sometimes|required|integer|min:0|max:100',
                'actual_hours' => 'nullable|numeric|min:0',
            ]);
        } else {
            $validated = $request->validate([
                'project_id' => 'sometimes|required|exists:projects,id',
                'name' => 'sometimes|required|string|max:255',
                'code' => 'nullable|string|max:50|unique:tasks,code,' . $task->id,
                'assigned_employee_id' => 'nullable|exists:users,id',
                'priority' => 'sometimes|required|in:low,medium,high,urgent',
                'status' => 'sometimes|required|in:pending,in_progress,on_hold,completed,cancelled',
                'progress' => 'sometimes|required|integer|min:0|max:100',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'actual_hours' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
            ]);
        }

        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully.',
            'data' => new TaskApiResource($task->load(['project', 'assignedEmployee'])),
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if ($user && ! $user->hasRole(['admin', 'manager']) && ! $user->can('tasks.delete')) {
            return response()->json(['message' => 'Unauthorized to delete tasks.'], 403);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.',
        ], 200);
    }
}
