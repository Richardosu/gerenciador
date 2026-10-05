<?php

namespace App\Filament\Resources\Tasks;

use App\Actions\Tasks\TransitionTask;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\SubtasksRelationManager;
use App\Models\Project;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Tarefas';

    protected static string|\UnitEnum|null $navigationGroup = 'Gerenciamento';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'tarefa';

    protected static ?string $pluralModelLabel = 'tarefas';

    public static function form(Schema $schema): Schema
    {
        $canManageTask = fn (?Task $record): bool => $record === null || (bool) auth()->user()?->canManageProject($record->project);

        return $schema->components([
            TextInput::make('title')->label('Título')->required()->maxLength(255)->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            Select::make('project_id')
                ->label('Projeto')
                ->options(fn () => auth()->user()?->visibleProjectsQuery()
                    ->whereNotIn('status', [ProjectStatus::Completed, ProjectStatus::Cancelled])
                    ->orderBy('name')
                    ->pluck('name', 'id') ?? [])
                ->live()
                ->required()
                ->searchable()
                ->disabled(fn (?Task $record): bool => $record !== null),
            Select::make('assignee_id')
                ->label('Responsável')
                ->options(fn (callable $get) => Project::query()->find($get('project_id'))?->members()
                    ->orderBy('name')
                    ->pluck('name', 'users.id') ?? [])
                ->searchable()
                ->nullable()
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            Select::make('priority')
                ->label('Prioridade')
                ->options(Priority::class)
                ->default(Priority::Medium)
                ->required()
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            Select::make('status')
                ->label('Status')
                ->options(TaskStatus::class)
                ->default(TaskStatus::Backlog)
                ->disabled(fn (?Task $record): bool => $record !== null)
                ->dehydrated()
                ->required(),
            DatePicker::make('start_date')->label('Data de início')->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            DatePicker::make('due_date')
                ->label('Data limite')
                ->afterOrEqual('start_date')
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            TextInput::make('estimated_hours')
                ->label('Estimativa de horas')
                ->numeric()
                ->minValue(0)
                ->step('0.25')
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            Textarea::make('description')
                ->label('Descrição')
                ->rows(5)
                ->columnSpanFull()
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record)),
            SpatieMediaLibraryFileUpload::make('attachments')
                ->label('Anexos')
                ->collection('attachments')
                ->multiple()
                ->reorderable()
                ->downloadable()
                ->openable()
                ->maxSize(20480)
                ->disabled(fn (?Task $record): bool => ! $canManageTask($record))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Tarefa')->searchable()->sortable(),
                TextColumn::make('project.name')->label('Projeto')->searchable()->sortable(),
                TextColumn::make('assignee.name')->label('Responsável')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('priority')->label('Prioridade')->badge()->sortable(),
                TextColumn::make('due_date')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Task $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Task $record): ?string => $record->isOverdue() ? 'Atrasada' : null),
            ])
            ->filters([
                SelectFilter::make('project_id')->label('Projeto')->relationship('project', 'name'),
                SelectFilter::make('assignee_id')->label('Responsável')->relationship('assignee', 'name'),
                SelectFilter::make('status')->label('Status')->options(TaskStatus::class),
                SelectFilter::make('priority')->label('Prioridade')->options(Priority::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (Task $record): bool => auth()->user()?->can('view', $record)),
                Action::make('start')
                    ->label(fn (Task $record): string => $record->status === TaskStatus::Backlog ? 'Mover para A fazer' : 'Iniciar tarefa')
                    ->icon('heroicon-o-play')
                    ->visible(fn (Task $record): bool => in_array($record->status, [TaskStatus::Backlog, TaskStatus::Todo], true) && auth()->user()?->can('changeStatus', $record))
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, $record->status === TaskStatus::Backlog ? TaskStatus::Todo : TaskStatus::InProgress, auth()->user())),
                Action::make('review')
                    ->label('Enviar para revisão')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::InProgress && auth()->user()?->can('changeStatus', $record))
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::InReview, auth()->user())),
                Action::make('complete')
                    ->label('Concluir tarefa')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::InReview && auth()->user()?->can('changeStatus', $record))
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::Completed, auth()->user())),
                Action::make('reopen')
                    ->label('Reabrir tarefa')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::Completed && auth()->user()?->can('changeStatus', $record))
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::Todo, auth()->user())),
                Action::make('cancel')
                    ->label('Cancelar tarefa')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Task $record): bool => $record->status->isOpen() && auth()->user()?->can('changeStatus', $record))
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => app(TransitionTask::class)($record, TaskStatus::Cancelled, auth()->user())),
                EditAction::make()
                    ->visible(fn (Task $record): bool => auth()->user()?->can('update', $record)),
            ])
            ->defaultSort('due_date');
    }

    public static function getRelations(): array
    {
        return [SubtasksRelationManager::class, CommentsRelationManager::class];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Título'),
            TextEntry::make('project.name')->label('Projeto'),
            TextEntry::make('assignee.name')->label('Responsável'),
            TextEntry::make('description')->label('Descrição')->placeholder('Sem descrição')->columnSpanFull(),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('priority')->label('Prioridade')->badge(),
            TextEntry::make('due_date')->label('Prazo')->date('d/m/Y'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return auth()->user()?->visibleTasksQuery() ?? parent::getEloquentQuery()->whereRaw('1 = 0');
    }
}
