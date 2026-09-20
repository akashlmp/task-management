<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('tasks.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Manager of the project or manager of the project's assigned team
        if ($user->hasRole('manager')) {
            if ($task->project?->manager_id === $user->id) {
                return true;
            }

            if ($task->project?->team?->manager_id === $user->id) {
                return true;
            }
        }

        // Team Leader of the project's team or assigned to task
        if ($user->hasRole('team_leader')) {
            if ($task->project?->team?->team_leader_id === $user->id) {
                return true;
            }

            if ($task->assignees()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        // Team Member assigned to task or member of project
        if ($task->assignees()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return (bool) $task->project?->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('tasks.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Manager of project or team
        if ($user->hasRole('manager') && $user->hasPermissionTo('tasks.update')) {
            if ($task->project?->manager_id === $user->id || $task->project?->team?->manager_id === $user->id) {
                return true;
            }
        }

        // Team Leader of project team or assigned
        if ($user->hasRole('team_leader') && $user->hasPermissionTo('tasks.update')) {
            if ($task->project?->team?->team_leader_id === $user->id || $task->assignees()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        // Assigned member can update task status and progress
        if ($user->hasPermissionTo('tasks.update') && $task->assignees()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager') && $user->hasPermissionTo('tasks.delete')) {
            if ($task->project?->manager_id === $user->id || $task->project?->team?->manager_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
