<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $projects = $user?->visibleProjectsQuery() ?? Project::query()->whereRaw('1 = 0');
        $tasks = $user?->visibleTasksQuery() ?? Task::query()->whereRaw('1 = 0');

        return [
            Stat::make('Projetos ativos', (clone $projects)->where('status', ProjectStatus::InProgress)->count())
                ->description('Projetos em andamento')
                ->color('primary'),
            Stat::make('Minhas tarefas', (clone $tasks)->assignedTo($user)->open()->count())
                ->description('Tarefas abertas atribuídas a você')
                ->color('info'),
            Stat::make('Tarefas atrasadas', (clone $tasks)->assignedTo($user)->overdue()->count())
                ->description('Tarefas abertas vencidas')
                ->color('danger'),
            Stat::make('Concluídas no mês', (clone $tasks)->assignedTo($user)->completedThisMonth()->count())
                ->description('Tarefas finalizadas neste mês')
                ->color('success'),
        ];
    }
}
