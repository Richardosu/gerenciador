<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class TransitionTask
{
    public function __invoke(Task $task, TaskStatus $nextStatus, User $actor): Task
    {
        return app(SetTaskStatus::class)($task, $nextStatus, $actor);
    }
}
