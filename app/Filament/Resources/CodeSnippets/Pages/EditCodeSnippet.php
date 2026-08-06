<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCodeSnippet extends EditRecord
{
    protected static string $resource = CodeSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
