<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingTasksWidget extends TableWidget
{
    protected static ?string $heading = 'Próximas tarefas';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => (auth()->user()?->visibleTasksQuery() ?? Task::query()->whereRaw('1 = 0'))
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', today())
                ->whereDate('due_date', '<=', today()->addDays(7))
                ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
                ->with(['project', 'assignee']))
            ->columns([
                TextColumn::make('title')->label('Tarefa')->searchable(),
                TextColumn::make('project.name')->label('Projeto'),
                TextColumn::make('assignee.name')->label('Responsável'),
                TextColumn::make('due_date')->label('Prazo')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10]);
    }
}
