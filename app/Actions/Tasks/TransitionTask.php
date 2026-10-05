<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionTask
{
    public function __invoke(Task $task, TaskStatus $nextStatus, User $actor): Task
    {
        Gate::forUser($actor)->authorize('changeStatus', $task);

        $allowedTransitions = match ($task->status) {
            TaskStatus::Backlog => [TaskStatus::Todo, TaskStatus::Cancelled],
            TaskStatus::Todo => [TaskStatus::InProgress, TaskStatus::Cancelled],
            TaskStatus::InProgress => [TaskStatus::InReview, TaskStatus::Cancelled],
            TaskStatus::InReview => [TaskStatus::Completed, TaskStatus::Cancelled],
            TaskStatus::Completed => [TaskStatus::Todo],
            default => [],
        };

        if (! in_array($nextStatus, $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => 'Esta transição de status não está disponível.',
            ]);
        }

        $task->update(['status' => $nextStatus]);

        return $task->refresh();
    }
}
