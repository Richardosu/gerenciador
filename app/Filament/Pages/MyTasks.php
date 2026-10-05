<?php

namespace App\Filament\Pages;

use App\Actions\Tasks\TransitionTask;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTasks extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.my-tasks';

    protected static ?string $navigationLabel = 'Minhas tarefas';

    protected static string|\UnitEnum|null $navigationGroup = 'Meu trabalho';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Task::query()->assignedTo(auth()->user()))
            ->columns([
                TextColumn::make('title')->label('Tarefa')->searchable()->sortable(),
                TextColumn::make('project.name')->label('Projeto')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('priority')->label('Prioridade')->badge(),
                TextColumn::make('due_date')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Task $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Task $record): ?string => $record->isOverdue() ? 'Atrasada' : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        TaskStatus::Backlog->value => TaskStatus::Backlog->getLabel(),
                        TaskStatus::Todo->value => TaskStatus::Todo->getLabel(),
                        TaskStatus::InProgress->value => TaskStatus::InProgress->getLabel(),
                        TaskStatus::InReview->value => TaskStatus::InReview->getLabel(),
                        TaskStatus::Completed->value => TaskStatus::Completed->getLabel(),
                    ]),
                Filter::make('overdue')
                    ->label('Atrasadas')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Iniciar tarefa')
                    ->icon('heroicon-o-play')
                    ->visible(fn (Task $record): bool => in_array($record->status, [TaskStatus::Backlog, TaskStatus::Todo], true))
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, $record->status === TaskStatus::Backlog ? TaskStatus::Todo : TaskStatus::InProgress, auth()->user())),
                Action::make('review')
                    ->label('Enviar para revisão')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::InProgress)
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::InReview, auth()->user())),
                Action::make('complete')
                    ->label('Concluir tarefa')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::InReview)
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::Completed, auth()->user())),
            ])
            ->recordUrl(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
            ->defaultSort('due_date');
    }
}
