<x-filament-panels::page>
    @php
        $tree = $this->getTree();
        $categories = $this->getAvailableCategories();
        $pages = $this->getAvailablePages();
    @endphp

    <div class="scbd-menu-editor">
        {{-- ── Add Items ──────────────────────────────────────────────── --}}
        <aside class="scbd-card scbd-add-items">
            <h3 class="scbd-card-title">Add Items</h3>

            <div x-data="{ open: 'custom' }" class="scbd-accordion">
                {{-- Custom Link --}}
                <div class="scbd-accordion-section">
                    <button type="button" class="scbd-accordion-head" x-on:click="open = open === 'custom' ? null : 'custom'">
                        <x-filament::icon icon="heroicon-o-link" />
                        <span>Custom Link</span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="scbd-accordion-chevron" ::class="open === 'custom' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open === 'custom'" x-collapse>
                        <div class="scbd-field">
                            <label for="new-label">Label</label>
                            <input id="new-label" type="text" wire:model="newLabel" placeholder="e.g., About Us" class="scbd-input">
                        </div>

                        <div class="scbd-field">
                            <label for="new-url">URL</label>
                            <input id="new-url" type="text" wire:model="newUrl" placeholder="e.g., /about or https://..." class="scbd-input">
                        </div>

                        <div class="scbd-field">
                            <label for="new-target">Open in</label>
                            <select id="new-target" wire:model="newTarget" class="scbd-select">
                                <option value="_self">Same window</option>
                                <option value="_blank">New window</option>
                            </select>
                        </div>

                        <button type="button" wire:click="addCustomLink" class="scbd-add-btn">
                            <x-filament::icon icon="heroicon-o-plus" />
                            Add to Menu
                        </button>
                    </div>
                </div>

                {{-- Pages --}}
                <div class="scbd-accordion-section">
                    <button type="button" class="scbd-accordion-head" x-on:click="open = open === 'pages' ? null : 'pages'">
                        <x-filament::icon icon="heroicon-o-document-text" />
                        <span>Pages ({{ $pages->count() }})</span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="scbd-accordion-chevron" ::class="open === 'pages' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open === 'pages'" x-cloak x-collapse>
                        @if ($pages->isEmpty())
                            {{-- Honest about why this is empty: a bare checkbox
                                 list would read as a loading failure. --}}
                            <p class="scbd-empty">No pages yet. Create one under Content &rsaquo; Pages.</p>
                        @else
                            <div class="scbd-checkboxes">
                                @foreach ($pages as $page)
                                    <label class="scbd-checkbox">
                                        <input type="checkbox" value="{{ $page->id }}" wire:model="selectedPages">
                                        <span>{{ $page->t('title') ?: $page->slug }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <button type="button" wire:click="addSelectedPages" class="scbd-add-btn">
                                <x-filament::icon icon="heroicon-o-plus" />
                                Add Selected ({{ count($selectedPages) }})
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Blog Categories --}}
                <div class="scbd-accordion-section">
                    <button type="button" class="scbd-accordion-head" x-on:click="open = open === 'categories' ? null : 'categories'">
                        <x-filament::icon icon="heroicon-o-rectangle-stack" />
                        <span>Blog Categories</span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="scbd-accordion-chevron" ::class="open === 'categories' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open === 'categories'" x-cloak x-collapse>
                        @if ($categories->isEmpty())
                            <p class="scbd-empty">No blog categories exist yet.</p>
                        @else
                            <p class="scbd-accordion-note">
                                Adds a “Blog” item with all {{ $categories->count() }}
                                {{ Str::plural('category', $categories->count()) }} as nested children.
                            </p>

                            <button type="button" wire:click="addBlogCategories" class="scbd-add-btn scbd-add-btn-solid">
                                <x-filament::icon icon="heroicon-o-rectangle-stack" />
                                Add Blog Categories
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </aside>

        {{-- ── Menu Structure ─────────────────────────────────────────── --}}
        <section
            class="scbd-card scbd-structure"
            x-data="menuTree({ saved: @js($tree->isNotEmpty()) })"
        >
            <div class="scbd-structure-head">
                <div>
                    <h3 class="scbd-card-title">
                        <x-filament::icon icon="heroicon-o-paper-airplane" />
                        Menu Structure
                    </h3>
                    <p class="scbd-card-sub">
                        <x-filament::icon icon="heroicon-o-bars-3" class="scbd-inline-icon" />
                        Drag items to reorder. Drop on an item to nest it.
                    </p>
                </div>

                <div class="scbd-structure-actions">
                    <button type="button" class="scbd-pill" x-on:click="expandAll()">
                        <x-filament::icon icon="heroicon-m-chevron-down" />
                        Expand
                    </button>
                    <button type="button" class="scbd-pill" x-on:click="collapseAll()">
                        <x-filament::icon icon="heroicon-m-chevron-up" />
                        Collapse
                    </button>
                </div>
            </div>

            @if ($tree->isEmpty())
                <p class="scbd-empty">This menu has no items yet. Add one from the panel on the left.</p>
            @endif

            <ul class="scbd-tree" data-menu-root>
                @foreach ($tree as $item)
                    @include('filament.pages.partials.menu-item', ['item' => $item, 'depth' => 0])
                @endforeach
            </ul>

            {{-- Dragging a nested item here pulls it back to the top level. --}}
            <div class="scbd-root-drop" data-menu-root-drop>
                Drop here to make a root-level item
            </div>
        </section>
    </div>
</x-filament-panels::page>
