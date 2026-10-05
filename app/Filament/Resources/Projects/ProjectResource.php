<?php

namespace App\Filament\Resources\Projects;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationLabel = 'Projetos';

    protected static string|\UnitEnum|null $navigationGroup = 'Gerenciamento';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'projeto';

    protected static ?string $pluralModelLabel = 'projetos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),
            Select::make('manager_id')
                ->label('Gestor responsável')
                ->options(fn () => User::query()
                    ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [RoleName::Manager->value, RoleName::Admin->value]))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->default(fn () => auth()->id())
                ->required()
                ->searchable()
                ->disabled(fn () => ! auth()->user()?->isAdmin())
                ->dehydrated(),
            Textarea::make('description')
                ->label('Descrição')
                ->rows(4)
                ->columnSpanFull(),
            DatePicker::make('start_date')
                ->label('Data de início')
                ->required(),
            DatePicker::make('end_date')
                ->label('Data prevista de término')
                ->afterOrEqual('start_date'),
            Select::make('status')
                ->label('Status')
                ->options(ProjectStatus::class)
                ->default(ProjectStatus::Planned)
                ->required(),
            Select::make('priority')
                ->label('Prioridade')
                ->options(Priority::class)
                ->default(Priority::Medium)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Projeto')->searchable()->sortable(),
                TextColumn::make('manager.name')->label('Gestor')->sortable()->searchable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('priority')->label('Prioridade')->badge()->sortable(),
                TextColumn::make('progress')
                    ->label('Progresso')
                    ->suffix('%')
                    ->state(fn (Project $record): int => $record->progress)
                    ->sortable(false),
                TextColumn::make('end_date')->label('Término previsto')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ProjectStatus::class),
                SelectFilter::make('priority')->label('Prioridade')->options(Priority::class),
                SelectFilter::make('manager_id')->label('Gestor')->relationship('manager', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Nome'),
            TextEntry::make('manager.name')->label('Gestor responsável'),
            TextEntry::make('description')->label('Descrição')->placeholder('Sem descrição')->columnSpanFull(),
            TextEntry::make('start_date')->label('Data de início')->date('d/m/Y'),
            TextEntry::make('end_date')->label('Término previsto')->date('d/m/Y'),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('priority')->label('Prioridade')->badge(),
            TextEntry::make('progress')->label('Progresso')->suffix('%'),
        ]);
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class, TasksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return $user
            ? $user->visibleProjectsQuery()
            : parent::getEloquentQuery()->whereRaw('1 = 0');
    }
}
