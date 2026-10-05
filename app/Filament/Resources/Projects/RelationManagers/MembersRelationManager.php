<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Membros';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('pivot.joined_at')->label('Adicionado em')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Adicionar membro')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord) ?? false),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remover')
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord) ?? false),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageProject($this->ownerRecord) ?? false),
            ]);
    }
}
