<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\CompleteProject;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Concluir projeto')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== ProjectStatus::Completed && auth()->user()?->canManageProject($this->record))
                ->action(fn () => app(CompleteProject::class)($this->record, auth()->user())),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->isAdmin()) {
            $data['manager_id'] = $this->record->manager_id;
        }

        return $data;
    }
}
