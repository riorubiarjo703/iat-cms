<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;
    use HasTranslatableFields;

    public const TYPE_SIMPLE = 'simple';

    public const TYPE_BUILDER = 'builder';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const TRANSLATABLE = ['title', 'content', 'seo_title', 'seo_description'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'builder_payload' => 'array',
        'published_at' => 'datetime',
        'is_homepage' => 'boolean',
    ];

    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            self::TYPE_SIMPLE => 'Standard page',
            self::TYPE_BUILDER => 'Page builder',
        ];
    }

    public function usesBuilder(): bool
    {
        return $this->type === self::TYPE_BUILDER;
    }

    /**
     * Published means published now — a future published_at is scheduled, not
     * live, and must not be reachable by URL yet.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * Marked published but dated in the future.
     *
     * Worth naming, because such a page is not reachable and the admin
     * previously reported it as simply "published" — which is how a page can
     * look live and 404.
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function getPublicUrl(): string
    {
        return $this->is_homepage ? url('/') : url('/'.$this->slug);
    }

    /**
     * The page serving "/", or null while the site still uses the hand-built
     * homepage. Only published pages qualify: an unpublished front page would
     * take the site down.
     */
    public static function homepage(): ?self
    {
        return static::query()->published()->where('is_homepage', true)->first();
    }

    /**
     * The block tree, always an array. A null payload and a malformed one both
     * mean "nothing to render" rather than an error — a page saved by an older
     * version must still load.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blocks(): array
    {
        $payload = $this->builder_payload;

        return is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if (blank($page->slug)) {
                $page->slug = static::uniqueSlug($page->t('title') ?? 'page');
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
