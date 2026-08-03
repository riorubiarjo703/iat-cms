<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @return array<int, \Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('build')
                ->label('Open the builder')
                ->icon('heroicon-o-squares-2x2')
                ->visible(fn (): bool => $this->record->usesBuilder())
                ->url(fn (): string => \App\Filament\Pages\BuildPage::getUrl(['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
