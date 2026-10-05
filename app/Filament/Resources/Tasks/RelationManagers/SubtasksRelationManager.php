<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Enums\SubtaskStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubtasksRelationManager extends RelationManager
{
    protected static string $relationship = 'subtasks';

    protected static ?string $title = 'Subtarefas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Título')->required()->maxLength(255),
            Select::make('status')->label('Situação')->options(SubtaskStatus::class)->default(SubtaskStatus::Pending)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Subtarefa')->searchable(),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar subtarefa')
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord->project) ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord->project) ?? false),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord->project) ?? false),
            ]);
    }
}
