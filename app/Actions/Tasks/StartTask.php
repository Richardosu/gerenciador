<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

class StartTask
{
    public function __invoke(Task $task): Task
    {
        if ($task->status !== TaskStatus::Todo) {
            throw ValidationException::withMessages([
                'status' => 'Somente tarefas com status "A fazer" podem ser iniciadas.',
            ]);
        }

        $task->update(['status' => TaskStatus::InProgress]);

        return $task->refresh();
    }
}
