<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    use HasFactory;
    // Aliased so the override below can still reach the original: a trait is
    // not a parent, so parent::t() does not resolve to it.
    use HasTranslatableFields {
        t as translateOwnField;
    }

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_PAGE = 'page';

    public const TYPE_CATEGORY = 'category';

    /**
     * Labels are a JSON column, the same as every other translatable model
     * here, so translation coverage discovers them with no extra wiring.
     */
    public const TRANSLATABLE = ['label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_cta' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['childrenRecursive', 'linkable']);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Ordinary navigation links — everything the CTA is not. */
    public function scopeLinks(Builder $query): Builder
    {
        return $query->active()->where('is_cta', false);
    }

    public function scopeCta(Builder $query): Builder
    {
        return $query->active()->where('is_cta', true);
    }

    /**
     * A linked item borrows the record's own translated title when no label
     * has been typed for this locale, so translations already entered on a
     * page or category are not retyped here.
     */
    public function t(string $key, ?string $locale = null): ?string
    {
        $own = $this->translateOwnField($key, $locale);

        if ($key !== 'label' || filled($own)) {
            return $own;
        }

        return $this->linkedTitle($locale);
    }

    /** Custom links carry their own URL; linked items follow the record. */
    public function resolveUrl(): string
    {
        if ($this->type === self::TYPE_CUSTOM) {
            return (string) $this->url;
        }

        $linked = $this->linkable;

        if ($linked === null) {
            // The target was deleted. An empty href would silently look like a
            // working link, so send it nowhere explicitly.
            return '#';
        }

        foreach (['getPublicUrl', 'publicUrl'] as $method) {
            if (method_exists($linked, $method)) {
                return (string) $linked->{$method}();
            }
        }

        return filled($this->url) ? (string) $this->url : '#';
    }

    private function linkedTitle(?string $locale): ?string
    {
        $linked = $this->linkable;

        if ($linked === null) {
            return null;
        }

        foreach (['title', 'name'] as $attribute) {
            if (method_exists($linked, 't')) {
                $translated = $linked->t($attribute, $locale);

                if (filled($translated)) {
                    return $translated;
                }
            }

            if (filled($linked->{$attribute} ?? null) && is_string($linked->{$attribute})) {
                return $linked->{$attribute};
            }
        }

        return null;
    }
}
