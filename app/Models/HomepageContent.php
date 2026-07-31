<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    use HasTranslatableFields;

    /**
     * Ordered list of translatable columns. Reused by the Filament form and the
     * i18n payload builder so the three never drift apart.
     *
     * @var array<int, string>
     */
    public const TRANSLATABLE = [
        'brand_sub',
        'hero_line',
        'hero_sub',
        'about_heading',
        'about_body',
        'about_cta_label',
        'district_heading',
        'district_body',
        'facilities_heading',
        'facilities_body',
        'news_heading',
        'news_cta_label',
        'contact_heading',
        'marquee_text',
    ];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    /**
     * There is only ever one homepage. Creating on read means a fresh database
     * renders the site instead of throwing.
     */
    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
