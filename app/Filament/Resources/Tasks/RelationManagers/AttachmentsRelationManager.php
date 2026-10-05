<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Anexos';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('collection_name', 'attachments'))
            ->columns([
                TextColumn::make('name')->label('Arquivo')->searchable(),
                TextColumn::make('mime_type')->label('Tipo'),
                TextColumn::make('human_readable_size')->label('Tamanho'),
                TextColumn::make('created_at')->label('Enviado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->authorize(fn (Media $record): bool => $record->model_id === $this->ownerRecord->getKey())
                    ->url(fn (Media $record): string => route('tasks.attachments.download', [
                        'task' => $this->ownerRecord,
                        'media' => $record,
                    ])),
                DeleteAction::make()
                    ->label('Remover')
                    ->authorize(fn (Media $record): bool => auth()->user()?->canManageProject($this->ownerRecord->project) ?? false),
            ]);
    }
}
