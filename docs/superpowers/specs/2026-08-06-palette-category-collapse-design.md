# Collapsible palette categories

Make each block-palette category header in the page builder toggle its item list open/closed. Categories start expanded; clicking the header collapses or expands independently.

## Context

`resources/views/filament/pages/build-page.blade.php` renders the left palette as:

```blade
@foreach ($this->getPalette() as $category => $items)
    <p class="scbd-palette-category">{{ $category }}</p>
    <div class="scbd-palette-items">…</div>
@endforeach
```

Category labels are static uppercase muted text (`.scbd-palette-category` in `resources/css/filament/admin/builder.css`). The menu editor already uses Alpine `x-data` + `x-show` + `x-collapse` for accordion sections (`edit-menu.blade.php`); this feature reuses that collapse mechanism without the single-open accordion constraint.

## Behaviour

- Default: every category is **open**.
- Click the category header to toggle that category only.
- Multiple categories may be open at once.
- State is client-only; reload resets to all open.
- No Livewire / PHP / persistence changes.

## Implementation

### Markup (`build-page.blade.php`)

Wrap each category loop body:

```blade
<div class="scbd-palette-group" x-data="{ open: true }">
    <button
        type="button"
        class="scbd-palette-category"
        x-on:click="open = !open"
        :aria-expanded="open.toString()"
    >
        <span>{{ $category }}</span>
        <x-filament::icon
            icon="heroicon-m-chevron-down"
            class="scbd-palette-category-chevron"
            ::class="open ? 'rotate-180' : ''"
        />
    </button>

    <div x-show="open" x-collapse>
        <div class="scbd-palette-items">
            {{-- existing item buttons unchanged --}}
        </div>
    </div>
</div>
```

Replace the non-interactive `<p>` with a `<button>` so keyboard and screen-reader users get a real control. Keep the existing visual class on the button.

### CSS (`builder.css`)

- Make `.scbd-palette-category` a full-width flex row (label + chevron), cursor pointer, inherit existing uppercase/muted typography.
- Style `.scbd-palette-category-chevron` like `.scbd-accordion-chevron` (muted, 150ms rotate).
- Keep `.scbd-palette-category:first-of-type { margin-top: 0 }` working via the group or by targeting the first group’s header.

## Out of scope

- Remembering collapsed state across visits.
- Accordion “only one open” behaviour.
- Changes to block add / canvas behaviour.

## Verification

Manual: open page builder, confirm all categories expanded; click a header to collapse its items with `x-collapse` animation; click again to expand; other categories unaffected. No automated test required for this presentational Alpine toggle.
