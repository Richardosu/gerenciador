<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasLabel
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'A fazer',
            self::InProgress => 'Em andamento',
            self::InReview => 'Em revisão',
            self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog => 'gray',
            self::Todo => 'info',
            self::InProgress => 'warning',
            self::InReview => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
