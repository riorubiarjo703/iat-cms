<?php

namespace App\Filament\Pages\Placeholders;

class CommentsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Comments';

    protected static ?string $slug = 'comments';

    public static function getNavigationLabel(): string
    {
        return 'Comments';
    }

    public static function summary(): string
    {
        return 'Moderation queue for reader comments on blog posts.';
    }
}
