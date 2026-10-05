<?php

namespace App\Observers;

use App\Domain\EnsureProjectBusinessRules;
use App\Models\Project;

class ProjectObserver
{
    public function saving(Project $project): void
    {
        app(EnsureProjectBusinessRules::class)->validate($project);
    }

    public function saved(Project $project): void
    {
        if (! $project->manager_id) {
            return;
        }

        $project->members()->syncWithoutDetaching([
            $project->manager_id => ['joined_at' => now()],
        ]);
    }
}
