<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class SendTaskToReview
{
    public function __invoke(Task $task, User $actor): Task
    {
        return app(SetTaskStatus::class)($task, TaskStatus::InReview, $actor);
    }
}
