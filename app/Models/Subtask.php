<?php

namespace App\Models;

use App\Enums\SubtaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'title', 'status', 'position', 'completed_at'])]
class Subtask extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubtaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subtask $subtask): void {
            if ($subtask->status === SubtaskStatus::Completed) {
                $subtask->completed_at ??= now();
            } else {
                $subtask->completed_at = null;
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
