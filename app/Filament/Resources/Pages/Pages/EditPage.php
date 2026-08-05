<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Pages\BuildPage;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('build')
                ->label('Open the builder')
                ->icon('heroicon-o-squares-2x2')
                ->visible(fn (): bool => $this->record->usesBuilder())
                ->url(fn (): string => BuildPage::getUrl(['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
