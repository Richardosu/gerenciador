<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompleteProject
{
    public function __invoke(Project $project, User $actor): Project
    {
        Gate::forUser($actor)->authorize('update', $project);

        if (! $project->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
            ]);
        }

        $project->update(['status' => ProjectStatus::Completed]);

        return $project->refresh();
    }
}
