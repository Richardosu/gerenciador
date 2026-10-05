<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentários';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')->label('Comentário')->required()->rows(4)->maxLength(5000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Usuário'),
                TextColumn::make('body')->label('Comentário')->wrap()->limit(180),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar comentário')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ]);
    }
}
