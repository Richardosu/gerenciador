<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Observers\ProjectObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ProjectObserver::class)]
#[Fillable([
    'name',
    'description',
    'manager_id',
    'start_date',
    'end_date',
    'status',
    'priority',
])]
class Project extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('id', 'joined_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    public function canBeCompleted(): bool
    {
        return ! $this->tasks()
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->exists();
    }

    public function pendingTasksCount(): int
    {
        return $this->tasks()
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->count();
    }

    protected function progress(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $total = (int) ($this->tasks_count ?? $this->tasks()->count());

                if ($total === 0) {
                    return 0;
                }

                $completed = (int) ($this->completed_tasks_count ?? $this->tasks()
                    ->where('status', TaskStatus::Completed)
                    ->count());

                return (int) round(($completed / $total) * 100);
            },
        );
    }
}
