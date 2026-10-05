<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class StartTask
{
    public function __invoke(Task $task, User $actor): Task
    {
        return app(SetTaskStatus::class)($task, TaskStatus::InProgress, $actor);
    }
}
