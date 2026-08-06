<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCodeSnippet extends CreateRecord
{
    protected static string $resource = CodeSnippetResource::class;

    public function getHeading(): string
    {
        return 'Create Code Snippet';
    }

    public function getSubheading(): ?string
    {
        return 'Add a new script, style, or meta tag to inject into pages';
    }
}
