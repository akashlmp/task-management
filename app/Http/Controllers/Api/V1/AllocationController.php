<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllocationApiResource;
use App\Models\EmployeeProjectAllocation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AllocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EmployeeProjectAllocation::with(['employee', 'project']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        return AllocationApiResource::collection($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'project_id' => 'required|exists:projects,id',
            'allocation_percentage' => 'required|integer|min:1|max:100',
            'role' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Workload validation: <= 100%
        $employee = User::find($validated['employee_id']);
        $currentLoad = $employee->active_allocation_percentage;
        $requested = (int) $validated['allocation_percentage'];

        if (($currentLoad + $requested) > 100) {
            return response()->json([
                'message' => "Workload limit exceeded! Employee '{$employee->name}' is currently at {$currentLoad}% active allocation. Adding {$requested}% would exceed the 100% capacity limit.",
            ], 422);
        }

        $allocation = EmployeeProjectAllocation::create($validated);

        return response()->json([
            'message' => 'Project allocation created successfully.',
            'data' => new AllocationApiResource($allocation->load(['employee', 'project'])),
        ], 201);
    }

    public function show(EmployeeProjectAllocation $allocation): JsonResponse
    {
        return response()->json([
            'data' => new AllocationApiResource($allocation->load(['employee', 'project'])),
        ]);
    }

    public function update(Request $request, EmployeeProjectAllocation $allocation): JsonResponse
    {
        $validated = $request->validate([
            'allocation_percentage' => 'sometimes|required|integer|min:1|max:100',
            'role' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if (isset($validated['allocation_percentage'])) {
            $employee = $allocation->employee;
            $currentLoad = $employee->active_allocation_percentage - $allocation->allocation_percentage;
            $requested = (int) $validated['allocation_percentage'];

            if (($currentLoad + $requested) > 100) {
                return response()->json([
                    'message' => "Workload limit exceeded! Employee '{$employee->name}' is currently at {$currentLoad}% without this allocation. Updating to {$requested}% would exceed the 100% limit.",
                ], 422);
            }
        }

        $allocation->update($validated);

        return response()->json([
            'message' => 'Project allocation updated successfully.',
            'data' => new AllocationApiResource($allocation->load(['employee', 'project'])),
        ]);
    }

    public function destroy(EmployeeProjectAllocation $allocation): JsonResponse
    {
        $allocation->delete();

        return response()->json([
            'message' => 'Project allocation removed successfully.',
        ], 200);
    }
}
