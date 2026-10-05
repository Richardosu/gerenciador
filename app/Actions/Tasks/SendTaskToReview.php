<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

class SendTaskToReview
{
    public function __invoke(Task $task): Task
    {
        if ($task->status !== TaskStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'Somente tarefas em andamento podem ser enviadas para revisão.',
            ]);
        }

        $task->update(['status' => TaskStatus::InReview]);

        return $task->refresh();
    }
}
