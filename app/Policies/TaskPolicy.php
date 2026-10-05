<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isMember();
    }

    public function view(User $user, Task $task): bool
    {
        return $user->participatesIn($task->project);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->canManageProject($task->project)) {
            return true;
        }

        return $user->isMember() && (int) $task->assignee_id === (int) $user->id;
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $user->canManageProject($task->project)
            || ($user->isMember() && (int) $task->assignee_id === (int) $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->canManageProject($task->project);
    }

    public function restore(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }
}
