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

        $tasksOutsidePeriod = $project->tasks()
            ->where(function ($query) use ($project): void {
                $query
                    ->where(function ($query) use ($project): void {
                        $query
                            ->whereNotNull('start_date')
                            ->whereDate('start_date', '<', $project->start_date);
                    })
                    ->orWhere(function ($query) use ($project): void {
                        $query
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', $project->start_date);
                    });

                if ($project->end_date) {
                    $query
                        ->orWhere(function ($query) use ($project): void {
                            $query
                                ->whereNotNull('start_date')
                                ->whereDate('start_date', '>', $project->end_date);
                        })
                        ->orWhere(function ($query) use ($project): void {
                            $query
                                ->whereNotNull('due_date')
                                ->whereDate('due_date', '>', $project->end_date);
                        });
                }
            })
            ->exists();

        if ($tasksOutsidePeriod) {
            throw ValidationException::withMessages([
                'end_date' => 'As datas das tarefas precisam permanecer dentro do período do projeto.',
            ]);
        }

        if ($project->status === ProjectStatus::Completed && ! $project->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
            ]);
        }
    }
}
