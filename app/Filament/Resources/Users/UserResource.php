<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Usuários';

    protected static string|\UnitEnum|null $navigationGroup = 'Administração';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(12),
            Select::make('roles')
                ->label('Perfil')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->required(),
            Toggle::make('is_active')->label('Usuário ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Perfis')->badge(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
                TextColumn::make('created_at')->label('Criado em')->date('d/m/Y')->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Ativo')])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
