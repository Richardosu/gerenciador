<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubtaskStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Completed => 'Concluída',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
        };
    }
}
