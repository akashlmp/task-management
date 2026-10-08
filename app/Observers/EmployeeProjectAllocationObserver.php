<?php

namespace App\Observers;

use App\Models\EmployeeProjectAllocation;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class EmployeeProjectAllocationObserver
{
    /**
     * Handle the EmployeeProjectAllocation "saving" event.
     */
    public function saving(EmployeeProjectAllocation $allocation): void
    {
        $employeeId = $allocation->employee_id;
        if (! $employeeId) {
            return;
        }

        // Active projects are those not completed or cancelled
        $query = EmployeeProjectAllocation::where('employee_id', $employeeId)
            ->whereHas('project', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            });

        if ($allocation->exists) {
            $query->where('id', '!=', $allocation->id);
        }

        $currentTotal = (int) $query->sum('allocation_percentage');
        $proposedTotal = $currentTotal + (int) $allocation->allocation_percentage;

        if ($proposedTotal > 100) {
            $employee = User::find($employeeId);
            $name = $employee ? $employee->name : "Employee #{$employeeId}";

            throw ValidationException::withMessages([
                'allocation_percentage' => "Workload limit exceeded for {$name}! Total active allocation cannot exceed 100%. Currently active: {$currentTotal}%, requested: {$allocation->allocation_percentage}% (Total would be {$proposedTotal}%).",
            ]);
        }
    }

    /**
     * Handle the EmployeeProjectAllocation "saved" event.
     */
    public function saved(EmployeeProjectAllocation $allocation): void
    {
        $employee = $allocation->employee;
        if (! $employee) {
            return;
        }

        $totalActive = $employee->active_allocation_percentage;

        // Requirement 6: Trigger notifications for Allocation approaching 100%
        if ($totalActive >= 80) {
            Notification::make()
                ->title('High Workload Alert')
                ->body("Your active project allocation is now at {$totalActive}% capacity.")
                ->warning()
                ->icon('heroicon-o-exclamation-triangle')
                ->sendToDatabase($employee);

            // Notify Managers
            $managers = User::role(['manager', 'admin'])->get();
            foreach ($managers as $manager) {
                if ($manager->id !== $employee->id) {
                    Notification::make()
                        ->title('Employee Workload Alert')
                        ->body("Employee {$employee->name} has reached {$totalActive}% workload allocation.")
                        ->warning()
                        ->icon('heroicon-o-exclamation-triangle')
                        ->sendToDatabase($manager);
                }
            }
        }
    }
}
