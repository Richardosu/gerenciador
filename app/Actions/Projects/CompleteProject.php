<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

class CompleteProject
{
    public function __invoke(Project $project): Project
    {
        if (! $project->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
            ]);
        }

        $project->update(['status' => ProjectStatus::Completed]);

        return $project->refresh();
    }
}
