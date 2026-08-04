<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasTranslatableFields;

    /**
     * Locale codes and their display labels. Single source of truth — the
     * Filament locale tabs and the i18n payload builder both read this.
     *
     * @var array<string, string>
     */
    public const LOCALES = [
        'en' => 'English',
        'id' => 'Indonesian',
        'cn' => '中文',
    ];

    /** @var array<int, string> */
    public const TRANSLATABLE = ['meta_title', 'meta_description', 'brand_subtitle'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'available_locales' => 'array',
        'social' => 'array',
    ];

    protected $attributes = [
        'default_locale' => 'en',
    ];

    public static function singleton(): self
    {
        // Read on every block view, the header, the footer and the translation
        // payload — fourteen identical queries on one homepage render before
        // this. Writes flush it, so a save is still visible in the same
        // request (the admin saves and re-renders in one round trip).
        return \App\Support\RequestCache::remember(
            'site_settings',
            fn (): self => static::query()->firstOrCreate(
                ['id' => 1],
                ['available_locales' => array_keys(self::LOCALES)],
            ),
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\RequestCache::flush('site_settings'));
        static::deleted(fn () => \App\Support\RequestCache::flush('site_settings'));
    }
}
