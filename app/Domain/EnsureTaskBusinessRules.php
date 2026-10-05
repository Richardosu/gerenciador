<?php

namespace App\Domain;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

class EnsureTaskBusinessRules
{
    public function validate(Task $task): void
    {
        $project = $task->project ?? Project::query()->find($task->project_id);

        if (! $project) {
            throw ValidationException::withMessages([
                'project_id' => 'Toda tarefa deve pertencer a um projeto.',
            ]);
        }

        if ($task->exists === false && $project->isLocked()) {
            throw ValidationException::withMessages([
                'project_id' => 'Projetos concluídos ou cancelados não podem receber novas tarefas.',
            ]);
        }

        if ($task->assignee_id) {
            $isMember = $project->members()->where('users.id', $task->assignee_id)->exists();

            if (! $isMember && (int) $project->manager_id !== (int) $task->assignee_id) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'O responsável pela tarefa deve ser membro do respectivo projeto.',
                ]);
            }
        }

        if ($task->due_date) {
            if ($task->due_date->lt($project->start_date)) {
                throw ValidationException::withMessages([
                    'due_date' => 'A data limite deve respeitar o período do projeto.',
                ]);
            }

            if ($project->end_date && $task->due_date->gt($project->end_date)) {
                throw ValidationException::withMessages([
                    'due_date' => 'A data limite deve respeitar o período do projeto.',
                ]);
            }
        }

        if ($task->start_date) {
            if ($task->start_date->lt($project->start_date)) {
                throw ValidationException::withMessages([
                    'start_date' => 'A data de início da tarefa deve respeitar o período do projeto.',
                ]);
            }

            if ($project->end_date && $task->start_date->gt($project->end_date)) {
                throw ValidationException::withMessages([
                    'start_date' => 'A data de início da tarefa deve respeitar o período do projeto.',
                ]);
            }
        }

        if ($task->start_date && $task->due_date && $task->due_date->lt($task->start_date)) {
            throw ValidationException::withMessages([
                'due_date' => 'A data limite deve ser igual ou posterior ao início da tarefa.',
            ]);
        }
    }
}
