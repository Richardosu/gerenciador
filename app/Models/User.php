<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps()
            ->withPivot('id', 'joined_at');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin);
    }

    public function isManager(): bool
    {
        return $this->hasRole(RoleName::Manager);
    }

    public function isMember(): bool
    {
        return $this->hasRole(RoleName::Member);
    }

    public function visibleProjectsQuery()
    {
        $query = Project::query();

        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isManager()) {
            return $query->where('manager_id', $this->id);
        }

        return $query->whereHas('members', fn ($members) => $members->where('users.id', $this->id));
    }

    public function visibleTasksQuery()
    {
        $query = Task::query();

        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isManager()) {
            return $query->whereHas('project', fn ($project) => $project->where('manager_id', $this->id));
        }

        if ($this->isMember()) {
            return $query->where('assignee_id', $this->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canManageProject(Project $project): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isManager() && $project->manager_id === $this->id;
    }

    public function participatesIn(Project $project): bool
    {
        if ($this->canManageProject($project)) {
            return true;
        }

        return $project->members()->where('users.id', $this->id)->exists();
    }
}
