<?php

namespace App\Actions\Tasks;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetTaskStatus
{
    public function __invoke(Task $task, TaskStatus $nextStatus, User $actor): Task
    {
        Gate::forUser($actor)->authorize('changeStatus', $task);

        return DB::transaction(function () use ($task, $nextStatus): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $currentStatus = $lockedTask->status;

            $allowedTransitions = match ($currentStatus) {
                TaskStatus::Backlog => [TaskStatus::Todo, TaskStatus::Cancelled],
                TaskStatus::Todo => [TaskStatus::InProgress, TaskStatus::Cancelled],
                TaskStatus::InProgress => [TaskStatus::InReview, TaskStatus::Cancelled],
                TaskStatus::InReview => [TaskStatus::Completed, TaskStatus::Cancelled],
                TaskStatus::Completed => [TaskStatus::Todo],
                TaskStatus::Cancelled => [],
            };

            if (! in_array($nextStatus, $allowedTransitions, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Esta transição de status não está disponível.',
                ]);
            }

            if ($nextStatus === TaskStatus::Completed) {
                $lockedTask->update([
                    'status' => $nextStatus,
                    'completed_at' => now(),
                ]);

                return $lockedTask->refresh();
            }

            $lockedTask->update([
                'status' => $nextStatus,
                'completed_at' => null,
            ]);

            if ($currentStatus === TaskStatus::Completed) {
                $project = $lockedTask->project;

                if ($project->status === ProjectStatus::Completed) {
                    $project->update(['status' => ProjectStatus::InProgress]);
                }
            }

            return $lockedTask->refresh();
        });
    }
}
