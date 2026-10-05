<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Widgets\ChartWidget;

class TasksByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Tarefas por status';

    protected function getData(): array
    {
        $query = auth()->user()?->visibleTasksQuery() ?? Task::query()->whereRaw('1 = 0');
        $counts = $query->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'datasets' => [[
                'label' => 'Tarefas',
                'data' => array_map(fn (TaskStatus $status): int => (int) ($counts[$status->value] ?? 0), TaskStatus::cases()),
                'backgroundColor' => ['#94a3b8', '#38bdf8', '#fbbf24', '#818cf8', '#4ade80', '#f87171'],
            ]],
            'labels' => array_map(fn (TaskStatus $status): string => $status->getLabel(), TaskStatus::cases()),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
