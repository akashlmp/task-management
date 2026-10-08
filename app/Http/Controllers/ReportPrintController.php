<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportPrintController extends Controller
{
    public function show(Request $request): View
    {
        $projects = Project::with(['projectManager', 'tasks', 'allocations.employee'])->get();
        $employees = User::where('status', 'active')
            ->with(['department', 'allocations.project'])
            ->get();
        $departments = Department::withCount('employees')->get();

        $stats = [
            'total_projects' => Project::count(),
            'active_projects' => Project::active()->count(),
            'completed_projects' => Project::where('status', 'completed')->count(),
            'total_budget' => Project::sum('budget'),
            'total_tasks' => Task::count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'pending_tasks' => Task::whereIn('status', ['pending', 'in_progress', 'on_hold'])->count(),
            'overdue_tasks' => Task::overdue()->count(),
            'total_employees' => $employees->count(),
        ];

        return view('reports.printable-summary', [
            'projects' => $projects,
            'employees' => $employees,
            'departments' => $departments,
            'stats' => $stats,
            'generatedAt' => now()->format('F d, Y - H:i T'),
            'generatedBy' => auth()->user()?->name ?? 'System Administrator',
        ]);
    }
}
