<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('projects.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Manager of project or manager of the project's assigned team
        if ($user->hasRole('manager')) {
            if ($project->manager_id === $user->id) {
                return true;
            }

            if ($project->team && $project->team->manager_id === $user->id) {
                return true;
            }
        }

        // Team leader of the project's team
        if ($user->hasRole('team_leader')) {
            if ($project->team && $project->team->team_leader_id === $user->id) {
                return true;
            }
        }

        // Member assigned to this project
        return $project->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('manager') && $user->hasPermissionTo('projects.create'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager') && $user->hasPermissionTo('projects.update')) {
            if ($project->manager_id === $user->id) {
                return true;
            }

            if ($project->team && $project->team->manager_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
