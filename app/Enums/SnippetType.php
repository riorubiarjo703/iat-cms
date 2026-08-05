<?php

namespace App\Enums;

/**
 * What a snippet contains. Placement is `SnippetPosition`'s job — this only
 * categorises, because the `code` column holds full tags rather than a bare
 * body, so nothing here has to wrap the operator's markup.
 */
enum SnippetType: string
{
    case Script = 'script';
    case Style = 'style';
    case Meta = 'meta';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Script => 'Script',
            self::Style => 'Style',
            self::Meta => 'Meta',
            self::Html => 'HTML',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Script => 'heroicon-o-code-bracket',
            self::Style => 'heroicon-o-paint-brush',
            self::Meta => 'heroicon-o-hashtag',
            self::Html => 'heroicon-o-document-text',
        };
    }

    /**
     * Options map for Filament `Select::options()`.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
