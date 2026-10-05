<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

class CompleteTask
{
    public function __invoke(Task $task): Task
    {
        if ($task->status !== TaskStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'Somente tarefas em revisão podem ser concluídas por esta ação.',
            ]);
        }

        $task->update(['status' => TaskStatus::Completed]);

        return $task->refresh();
    }
}
