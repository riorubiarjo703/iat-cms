<?php

namespace App\Models;

use App\Support\MenuLocations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reusable menu template. It exists independently of where it is shown; the
 * `location` column is the assignment, and is unique so a location can never
 * display two menus.
 */
class Menu extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort');
    }

    /** Top-level items only; children hang off these. */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /** The whole tree in one query, ready to render. */
    public function tree(): \Illuminate\Support\Collection
    {
        return $this->rootItems()
            ->with(['childrenRecursive', 'linkable'])
            ->get();
    }

    /**
     * How many of this menu's items a visitor actually sees.
     *
     * Not items_count: that counts rows, and a row whose page is still a draft
     * is not a link on the site. The admin reports both, because the gap
     * between them is the thing worth knowing.
     */
    public function liveItemCount(): int
    {
        return $this->countVisible($this->rootItems()->with(['childrenRecursive', 'linkable'])->get());
    }

    /**
     * Recurses only into visible parents: the site never reaches the children
     * of a hidden item, so counting them would overstate what is live.
     *
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     */
    private function countVisible(\Illuminate\Support\Collection $items): int
    {
        return $items
            ->filter(fn (MenuItem $item): bool => $item->isVisible())
            ->sum(fn (MenuItem $item): int => 1 + $this->countVisible($item->loadedChildren()));
    }

    public function scopeForLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', $location);
    }

    public static function assignedTo(string $location): ?self
    {
        if (! MenuLocations::exists($location)) {
            return null;
        }

        return \App\Support\RequestCache::remember(
            "menu.assigned.{$location}",
            fn (): ?self => static::forLocation($location)->first(),
        );
    }

    /**
     * Assigning a location takes it from whichever menu held it. Without this
     * the unique index would simply reject the save, which reads as a bug to
     * anyone using the dropdown.
     */
    public function assignLocation(?string $location): void
    {
        if ($location !== null && ! MenuLocations::exists($location)) {
            return;
        }

        if ($location !== null) {
            static::query()->where('location', $location)->whereKeyNot($this->getKey())->update(['location' => null]);
        }

        $this->update(['location' => $location]);
    }

    public function directive(): string
    {
        return "@menu('{$this->slug}')";
    }

    protected static function booted(): void
    {
        // Any menu write invalidates every cached location and tree: an
        // assignment moves a location between menus, and the admin saves and
        // re-renders within one request.
        static::saved(fn () => \App\Support\RequestCache::flush('menu.'));
        static::deleted(fn () => \App\Support\RequestCache::flush('menu.'));

        static::creating(function (self $menu): void {
            $menu->slug = filled($menu->slug) ? Str::slug($menu->slug) : static::uniqueSlug($menu->name);
        });
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
