<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use App\Models\Page;
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

    protected static function booted(): void
    {
        // A self-parented row vanishes from the tree and makes any recursive
        // walk loop. Refused here so no caller can create one, however it
        // arrives — a seeder, an import or a crafted request.
        static::saving(function (self $item): void {
            if ($item->parent_id !== null && (string) $item->parent_id === (string) $item->getKey()) {
                $item->parent_id = null;
            }
        });

        static::saved(fn () => \App\Support\RequestCache::flush('menu.'));
        static::deleted(fn () => \App\Support\RequestCache::flush('menu.'));
    }

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

    /**
     * The children to read when walking a tree.
     *
     * A tree is eager-loaded through `childrenRecursive`, which is a different
     * relation from `children` as far as Eloquent is concerned: reading
     * `children` on a loaded tree issues a query per row and turns rendering
     * the header into an N+1. Every walk goes through here instead.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function loadedChildren(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->relationLoaded('childrenRecursive')
            ? $this->childrenRecursive
            : $this->children;
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this item should appear on the site.
     *
     * An item linked to an unpublished page is hidden: the menu can be built
     * ahead of the content, and each entry appears by itself the moment its
     * page goes live, rather than sitting there as a link to a 404.
     */
    public function isVisible(int $depth = 0): bool
    {
        return $this->hiddenReason($depth) === null;
    }

    /**
     * Why this item does not appear on the site, or null when it does.
     *
     * isVisible() is derived from this rather than the two being written out
     * separately: an item the admin calls live while the site omits it — or
     * the reverse — is the bug this method exists to make impossible.
     */
    public function hiddenReason(int $depth = 0): ?string
    {
        if (! $this->is_active) {
            return 'Switched off';
        }

        $linked = $this->linkable;

        if ($linked instanceof Page) {
            return $linked->isPublished() ? null : 'Its page is a draft';
        }

        // A linked record that has been deleted leaves nothing to point at.
        if ($this->type !== self::TYPE_CUSTOM && $this->linkable_type !== null && $linked === null) {
            return 'The record it linked to no longer exists';
        }

        // A heading exists to group its children. With every child hidden it
        // is a dead entry pointing at "#", so it hides with them. The depth
        // guard stops a malformed tree from recursing forever.
        $children = $this->loadedChildren();

        if ($children->isNotEmpty() && blank($this->url) === false && $this->url === '#' && $depth < 10) {
            return $children->contains(fn (self $child): bool => $child->isVisible($depth + 1))
                ? null
                : 'All of its links are hidden';
        }

        return null;
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
            $url = (string) $this->url;

            // A bare fragment names a section of the homepage, which is the
            // only page that has one. Left as "#about" the browser looked for
            // that id in whatever page you were on, so every one of these was
            // dead outside the homepage. "#" alone is excluded: that is the
            // heading marker, not a section.
            if (strlen($url) > 1 && str_starts_with($url, '#')) {
                return rtrim(route('home'), '/').'/'.$url;
            }

            return $url;
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
