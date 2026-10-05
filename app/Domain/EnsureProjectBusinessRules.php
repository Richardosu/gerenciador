<?php

namespace App\Domain;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

class EnsureProjectBusinessRules
{
    public function validate(Project $project): void
    {
        if (
            $project->end_date
            && $project->start_date
            && $project->end_date->lt($project->start_date)
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'A data prevista de término deve ser posterior ao início.',
            ]);
        }

        if ($project->status === ProjectStatus::Completed && ! $project->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
            ]);
        }
    }
}
