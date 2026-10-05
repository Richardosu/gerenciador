<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(TaskObserver::class)]
#[Fillable([
    'project_id',
    'assignee_id',
    'created_by',
    'title',
    'description',
    'priority',
    'status',
    'start_date',
    'due_date',
    'completed_at',
    'estimated_hours',
])]
class Task extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TaskStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assignee_id', $user->id);
    }

    public function scopeCompletedThisMonth(Builder $query): Builder
    {
        return $query
            ->where('status', TaskStatus::Completed)
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled]);
    }
}
