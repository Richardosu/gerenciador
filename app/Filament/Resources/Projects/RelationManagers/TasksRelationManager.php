<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tarefas';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tarefa')
                    ->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
                    ->searchable(),
                TextColumn::make('assignee.name')->label('Responsável'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('priority')->label('Prioridade')->badge(),
                TextColumn::make('due_date')->label('Prazo')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('due_date');
    }
}
