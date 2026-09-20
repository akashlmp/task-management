<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id === $model->id) {
            return true;
        }

        if ($user->hasRole('manager')) {
            // Manager can view members in their managed teams as well as their team leaders
            $managedTeams = $user->managedTeams;
            if ($managedTeams->contains('team_leader_id', $model->id)) {
                return true;
            }

            return $model->teams()->whereIn('teams.id', $managedTeams->pluck('id'))->exists();
        }

        if ($user->hasRole('team_leader')) {
            // Team leader can view members in their led teams as well as their team managers
            $ledTeams = $user->ledTeams;
            if ($ledTeams->contains('manager_id', $model->id)) {
                return true;
            }

            return $model->teams()->whereIn('teams.id', $ledTeams->pluck('id'))->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can edit their own profile
        if ($user->id === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only admin can delete, and cannot delete own account
        return $user->hasRole('admin') && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
