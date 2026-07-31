<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicMenuItem extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'is_cta' => 'boolean',
        'sort' => 'integer',
    ];

    /** Ordinary navigation links — the reference's nav1..nav4. */
    public function scopeLinks(Builder $query): Builder
    {
        return $query->active()->where('is_cta', false)->ordered();
    }

    /** The header call-to-action button — the reference's `cta` key. */
    public function scopeCta(Builder $query): Builder
    {
        return $query->active()->where('is_cta', true)->ordered();
    }
}
