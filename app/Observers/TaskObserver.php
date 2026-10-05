<?php

namespace App\Observers;

use App\Domain\EnsureTaskBusinessRules;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Notifications\Notification;

class TaskObserver
{
    public function saving(Task $task): void
    {
        if ($task->status === TaskStatus::Completed) {
            $task->completed_at ??= now();
        } else {
            $task->completed_at = null;
        }

        app(EnsureTaskBusinessRules::class)->validate($task);
    }

    public function created(Task $task): void
    {
        $this->notifyAssignee($task);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('assignee_id')) {
            $this->notifyAssignee($task);
        }

        if ($task->wasChanged('status') && $task->status === TaskStatus::Completed) {
            $this->notifyCompleted($task);
        }
    }

    private function notifyAssignee(Task $task): void
    {
        if (! $task->assignee_id) {
            return;
        }

        if (auth()->id() && (int) $task->assignee_id === (int) auth()->id()) {
            return;
        }

        $assignee = $task->assignee ?? $task->assignee()->first();

        if (! $assignee) {
            return;
        }

        Notification::make()
            ->title('Tarefa atribuída')
            ->body("A tarefa \"{$task->title}\" foi atribuída a você.")
            ->success()
            ->sendToDatabase($assignee);
    }

    private function notifyCompleted(Task $task): void
    {
        $task->loadMissing(['project.manager', 'assignee']);

        $recipients = collect([
            $task->project?->manager,
            $task->assignee,
        ])
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => auth()->id() && (int) $user->id === (int) auth()->id())
            ->values();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Tarefa concluída')
                ->body("A tarefa \"{$task->title}\" foi concluída.")
                ->success()
                ->sendToDatabase($recipient);
        }
    }
}
