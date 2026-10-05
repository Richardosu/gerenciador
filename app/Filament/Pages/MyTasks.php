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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class MyTasks extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.my-tasks';

    protected static ?string $navigationLabel = 'Minhas tarefas';

    protected static string|\UnitEnum|null $navigationGroup = 'Meu trabalho';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 1;

    #[Url(as: 'tab', except: 'all')]
    public string $activeTab = 'all';

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'todo', 'in_progress', 'in_review', 'overdue', 'completed'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getTasksQuery())
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

    private function getTasksQuery(): Builder
    {
        $query = Task::query()->assignedTo(auth()->user());

        return match ($this->activeTab) {
            'todo' => $query->where('status', TaskStatus::Todo),
            'in_progress' => $query->where('status', TaskStatus::InProgress),
            'in_review' => $query->where('status', TaskStatus::InReview),
            'overdue' => $query->overdue(),
            'completed' => $query->where('status', TaskStatus::Completed),
            default => $query,
        };
    }
}
