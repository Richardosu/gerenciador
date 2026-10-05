<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Planejado',
            self::InProgress => 'Em andamento',
            self::Paused => 'Pausado',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
            self::Paused => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
