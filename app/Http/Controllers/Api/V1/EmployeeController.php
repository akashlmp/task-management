<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeApiResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::with(['department', 'roles', 'allocations.project']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return EmployeeApiResource::collection($query->paginate(15));
    }

    public function show(User $employee): JsonResponse
    {
        return response()->json([
            'data' => new EmployeeApiResource($employee->load(['department', 'roles', 'allocatedProjects', 'assignedTasks.project'])),
        ]);
    }

    public function update(Request $request, User $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'designation' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data' => new EmployeeApiResource($employee->load(['department', 'roles'])),
        ]);
    }
}
