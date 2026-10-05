<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tarefas';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->label('Criar tarefa')
                    ->form([
                        TextInput::make('title')->label('Título')->required()->maxLength(255),
                        Textarea::make('description')->label('Descrição')->rows(3),
                        Select::make('assignee_id')
                            ->label('Responsável')
                            ->options(fn () => $this->ownerRecord->members()->orderBy('name')->pluck('name', 'users.id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('priority')->label('Prioridade')->options(Priority::class)->default(Priority::Medium)->required(),
                        Select::make('status')->label('Status')->options(TaskStatus::class)->default(TaskStatus::Backlog)->required(),
                        DatePicker::make('start_date')->label('Data de início'),
                        DatePicker::make('due_date')->label('Data limite'),
                        TextInput::make('estimated_hours')->label('Estimativa de horas')->numeric()->minValue(0)->step('0.25'),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()])
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord) ?? false),
            ])
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = auth()->user();

                if ($user?->canManageProject($this->ownerRecord)) {
                    return $query;
                }

                return $query->where('assignee_id', $user?->id);
            })
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
