<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodeSnippets extends ListRecords
{
    protected static string $resource = CodeSnippetResource::class;

    public function getHeading(): string
    {
        return 'Code Snippets';
    }

    public function getSubheading(): ?string
    {
        return 'Inject scripts, styles, and meta tags into your pages';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Snippet'),
        ];
    }
}
