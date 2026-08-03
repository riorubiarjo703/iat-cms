<?php

namespace App\Filament\Pages;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The menu builder: pick items on the left, arrange them on the right.
 *
 * The tree is persisted from a single flat payload the browser sends after a
 * drag, rather than one request per moved row — a nested reorder can change
 * many rows at once, and applying them piecemeal would leave the tree
 * momentarily inconsistent.
 */
class EditMenuPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $slug = 'navigation-menus/{record}';

    protected string $view = 'filament.pages.edit-menu';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * The id, not the model. A public Eloquent property is serialised into the
     * Livewire payload and then handed back to mount() as JSON on the next
     * request, which fails as a primary key.
     */
    public int|string $recordId;

    protected ?Menu $cachedRecord = null;

    /** Custom-link form state. */
    public string $newLabel = '';

    public string $newUrl = '';

    public string $newTarget = '_self';

    /** @var array<int, string> */
    public array $selectedPages = [];

    // ── Inline edit state ───────────────────────────────────────────────
    public ?string $editingId = null;

    /** @var array<string, string> locale => label */
    public array $editLabel = [];

    public string $editUrl = '';

    public string $editTarget = '_self';

    public bool $editActive = true;

    public function mount(string|int $record): void
    {
        $this->recordId = $record;
        // Resolve now so a bad id 404s on load rather than mid-render.
        $this->getRecord();
    }

    public function getRecord(): Menu
    {
        return $this->cachedRecord ??= Menu::query()->findOrFail($this->recordId);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Drag and drop items to organize your menu';
    }

    /** @return array<int, array{label: string, url: string|null}> */
    public function getBreadcrumbs(): array
    {
        return [
            NavigationMenusPage::getUrl() => 'Menus',
            $this->getRecord()->name,
        ];
    }

    /** @return Collection<int, MenuItem> */
    public function getTree(): Collection
    {
        return $this->getRecord()->rootItems()->with(['childrenRecursive', 'linkable'])->get();
    }

    public function getLocationLabel(): ?string
    {
        return $this->getRecord()->location ? MenuLocations::label($this->getRecord()->location) : null;
    }

    // ── Adding items ────────────────────────────────────────────────────

    public function addCustomLink(): void
    {
        $label = trim($this->newLabel);
        $url = trim($this->newUrl);

        if ($label === '' || $url === '') {
            Notification::make()->title('A label and URL are both required')->danger()->send();

            return;
        }

        MenuItem::create([
            'menu_id' => $this->getRecord()->id,
            'type' => MenuItem::TYPE_CUSTOM,
            'label' => [MenuItem::FALLBACK_LOCALE => $label],
            'url' => $url,
            'target' => in_array($this->newTarget, ['_self', '_blank'], true) ? $this->newTarget : '_self',
            'sort' => $this->nextSort(),
        ]);

        $this->newLabel = '';
        $this->newUrl = '';
        $this->newTarget = '_self';

        Notification::make()->title('Added to menu')->success()->send();
    }

    /**
     * Adds a "Blog" parent with every category nested beneath it, preserving
     * the category hierarchy.
     */
    public function addBlogCategories(): void
    {
        $categories = BlogCategory::query()->orderBy('name')->get();

        if ($categories->isEmpty()) {
            Notification::make()->title('There are no blog categories yet')->warning()->send();

            return;
        }

        $parent = MenuItem::create([
            'menu_id' => $this->getRecord()->id,
            'type' => MenuItem::TYPE_CUSTOM,
            'label' => [MenuItem::FALLBACK_LOCALE => 'Blog'],
            'url' => '/blog',
            'sort' => $this->nextSort(),
        ]);

        foreach ($categories->values() as $index => $category) {
            MenuItem::create([
                'menu_id' => $this->getRecord()->id,
                'parent_id' => $parent->id,
                'type' => MenuItem::TYPE_CATEGORY,
                // Left empty on purpose: the item borrows the category's own
                // translated name, so renaming the category renames the link.
                'label' => [],
                'linkable_type' => BlogCategory::class,
                'linkable_id' => $category->getKey(),
                'sort' => $index + 1,
            ]);
        }

        Notification::make()->title("Added {$categories->count()} categories")->success()->send();
    }

    /** @return Collection<int, mixed> */
    public function getAvailableCategories(): Collection
    {
        return BlogCategory::query()->orderBy('name')->get();
    }

    /** @return Collection<int, \App\Models\Page> */
    public function getAvailablePages(): Collection
    {
        return \App\Models\Page::query()->orderBy('slug')->get();
    }

    /**
     * Adds the ticked pages as linked items. Labels are left empty so each
     * borrows the page's own translated title — renaming the page renames the
     * link, in every language.
     */
    public function addSelectedPages(): void
    {
        $pages = \App\Models\Page::query()->whereKey($this->selectedPages)->get();

        if ($pages->isEmpty()) {
            Notification::make()->title('Select at least one page')->warning()->send();

            return;
        }

        $sort = $this->nextSort();

        foreach ($pages as $page) {
            MenuItem::create([
                'menu_id' => $this->getRecord()->id,
                'type' => MenuItem::TYPE_PAGE,
                'label' => [],
                'linkable_type' => \App\Models\Page::class,
                'linkable_id' => $page->getKey(),
                'sort' => $sort++,
            ]);
        }

        $this->selectedPages = [];

        Notification::make()->title("Added {$pages->count()} ".Str::plural('page', $pages->count()))->success()->send();
    }

    // ── Inline editing ──────────────────────────────────────────────────

    /** @return array<string, string> */
    public function getLocales(): array
    {
        return \App\Models\SiteSetting::LOCALES;
    }

    public function startEditing(string $itemId): void
    {
        $item = $this->getRecord()->items()->whereKey($itemId)->first();

        if ($item === null) {
            return;
        }

        $this->editingId = (string) $item->getKey();

        // Every locale gets a key so the inputs bind, even the untranslated
        // ones — an absent key would silently discard what is typed into it.
        $stored = $item->translations('label');
        $this->editLabel = [];

        foreach (array_keys($this->getLocales()) as $locale) {
            $this->editLabel[$locale] = $stored[$locale] ?? '';
        }

        $this->editUrl = (string) $item->url;
        $this->editTarget = $item->target ?: '_self';
        $this->editActive = (bool) $item->is_active;
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->editLabel = [];
        $this->editUrl = '';
        $this->editTarget = '_self';
        $this->editActive = true;
    }

    public function saveItem(): void
    {
        $item = $this->getRecord()->items()->whereKey($this->editingId)->first();

        if ($item === null) {
            $this->cancelEditing();

            return;
        }

        // Blank locales are dropped rather than stored as empty strings, so
        // the fallback and the linked-record title still apply to them.
        $label = collect($this->editLabel)
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        // A custom link with no label anywhere would render as a blank row.
        if ($label === [] && $item->type === MenuItem::TYPE_CUSTOM) {
            Notification::make()->title('A label is required in at least one language')->danger()->send();

            return;
        }

        $item->update([
            'label' => $label,
            'url' => $item->type === MenuItem::TYPE_CUSTOM ? trim($this->editUrl) : $item->url,
            'target' => in_array($this->editTarget, ['_self', '_blank'], true) ? $this->editTarget : '_self',
            'is_active' => $this->editActive,
        ]);

        $this->cancelEditing();

        Notification::make()->title('Saved')->success()->send();
    }

    // ── Editing and removing ────────────────────────────────────────────

    public function deleteItem(string $itemId): void
    {
        $item = $this->getRecord()->items()->whereKey($itemId)->first();

        // Children cascade, so a parent takes its subtree with it.
        $item?->delete();
    }

    public function toggleActive(string $itemId): void
    {
        $item = $this->getRecord()->items()->whereKey($itemId)->first();

        $item?->update(['is_active' => ! $item->is_active]);
    }

    public function toggleCta(string $itemId): void
    {
        $item = $this->getRecord()->items()->whereKey($itemId)->first();

        if ($item === null) {
            return;
        }

        // At most one call-to-action per menu: two buttons competing for the
        // same slot is a template bug waiting to happen.
        if (! $item->is_cta) {
            $this->getRecord()->items()->where('is_cta', true)->update(['is_cta' => false]);
        }

        $item->update(['is_cta' => ! $item->is_cta]);
    }

    /**
     * Persists the whole tree from one payload.
     *
     * @param  array<int, array{id: string|int, parent: string|int|null, sort: int}>  $tree
     */
    public function saveTree(array $tree): void
    {
        $ownedIds = $this->getRecord()->items()->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($tree as $node) {
            $id = (string) ($node['id'] ?? '');
            $parent = $node['parent'] ?? null;
            $parent = ($parent === null || $parent === '' ) ? null : (string) $parent;

            // Ignore anything not belonging to this menu, and any parent that
            // is not either — a crafted payload must not be able to reparent
            // another menu's items.
            if (! in_array($id, $ownedIds, true)) {
                continue;
            }

            if ($parent !== null && ! in_array($parent, $ownedIds, true)) {
                continue;
            }

            // An item cannot be its own parent.
            if ($parent === $id) {
                continue;
            }

            MenuItem::query()->whereKey($id)->update([
                'parent_id' => $parent,
                'sort' => (int) ($node['sort'] ?? 0),
            ]);
        }
    }

    private function nextSort(): int
    {
        return (int) $this->getRecord()->items()->whereNull('parent_id')->max('sort') + 1;
    }
}
