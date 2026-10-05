<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isMember();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->participatesIn($project);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->canManageProject($project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->canManageProject($project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
