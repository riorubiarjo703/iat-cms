<x-filament-panels::page>
    @php
        $locations = $this->getLocations();
        $menus = $this->getMenus();
        $assignable = $this->getAssignableMenus();
        $hidden = $this->getHiddenCounts();
    @endphp

    {{-- ── Menu Locations ─────────────────────────────────────────────── --}}
    <section class="scbd-card scbd-menus-card">
        <div class="scbd-menus-intro">
            <span class="scbd-menus-intro-icon scbd-tint-blue">
                <x-filament::icon icon="heroicon-o-map-pin" />
            </span>
            <div>
                <h3 class="scbd-card-title">Menu Locations</h3>
                <p class="scbd-card-sub">Assign menus to display in different areas of your site</p>
            </div>
        </div>

        <div class="scbd-location-rows">
            @foreach ($locations as $key => $location)
                <div class="scbd-location-row" wire:key="location-{{ $key }}">
                    <span class="scbd-location-icon">
                        <x-filament::icon :icon="$location['icon']" />
                    </span>

                    <div class="scbd-location-body">
                        <p class="scbd-location-title">
                            {{ $location['label'] }}
                            {{-- An unassigned location reports nothing, not "0 items" — there is
                                 no menu to be empty. --}}
                            @if ($location['items'] !== null)
                                <span class="scbd-count-pill">{{ $location['items'] }} {{ Str::plural('item', $location['items']) }}</span>
                                {{-- Only when there is a gap. A location whose every item
                                     renders should not carry a "0 hidden" pill saying so. --}}
                                @if ($location['hidden'] > 0)
                                    <span class="scbd-count-pill scbd-count-pill-hidden"
                                          title="Switched off, or pointing at a page that is still a draft">
                                        {{ $location['hidden'] }} hidden
                                    </span>
                                @endif
                            @endif
                        </p>
                        <p class="scbd-location-desc">{{ $location['description'] }}</p>
                    </div>

                    <div class="scbd-location-assign">
                        <select
                            class="scbd-select"
                            wire:change="assignLocation('{{ $key }}', $event.target.value)"
                        >
                            <option value="">No menu assigned</option>
                            @foreach ($assignable as $option)
                                <option value="{{ $option['value'] }}" @selected($location['menu']?->getKey() == $option['value'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>

                        @if ($location['menu'])
                            <a
                                href="{{ \App\Filament\Pages\EditMenuPage::getUrl(['record' => $location['menu']->getKey()]) }}"
                                class="scbd-icon-btn"
                                aria-label="Open {{ $location['menu']->name }}"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" />
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Your Menus ─────────────────────────────────────────────────── --}}
    <section class="scbd-card scbd-menus-card">
        <div class="scbd-menus-intro">
            <span class="scbd-menus-intro-icon scbd-tint-blue">
                <x-filament::icon icon="heroicon-o-paper-airplane" />
            </span>
            <div>
                <h3 class="scbd-card-title">Your Menus</h3>
                <p class="scbd-card-sub">{{ $menus->count() }} {{ Str::plural('menu', $menus->count()) }} created</p>
            </div>
        </div>

        @if ($menus->isEmpty())
            <p class="scbd-empty">No menus yet. Create one to start building your navigation.</p>
        @else
            <div class="scbd-menu-rows">
                @foreach ($menus as $menu)
                    <div class="scbd-menu-row" wire:key="menu-{{ $menu->id }}">
                        <span class="scbd-menu-icon">
                            <x-filament::icon icon="heroicon-o-list-bullet" />
                        </span>

                        <div class="scbd-menu-body">
                            <p class="scbd-menu-title">
                                {{ $menu->name }}
                                @if ($menu->location)
                                    <span class="scbd-location-badge">
                                        <x-filament::icon :icon="$locations[$menu->location]['icon'] ?? 'heroicon-o-map-pin'" />
                                        {{ \App\Support\MenuLocations::label($menu->location) }}
                                    </span>
                                @endif
                            </p>
                            <p class="scbd-menu-meta">
                                {{ $menu->items_count }} {{ Str::plural('item', $menu->items_count) }}
                                @if (($hidden[$menu->id] ?? 0) > 0)
                                    <span class="scbd-count-pill scbd-count-pill-hidden"
                                          title="Switched off, or pointing at a page that is still a draft">
                                        {{ $hidden[$menu->id] }} hidden
                                    </span>
                                @endif
                                <span class="scbd-menu-dot">•</span>
                                <code
                                    class="scbd-directive"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($menu->directive())); copied = true; setTimeout(() => copied = false, 1500)"
                                    role="button"
                                    tabindex="0"
                                    x-bind:title="copied ? 'Copied' : 'Copy directive'"
                                >{{ $menu->directive() }}<x-filament::icon icon="heroicon-o-document-duplicate" class="scbd-directive-copy" /></code>
                            </p>
                        </div>

                        <div class="scbd-menu-actions">
                            <a href="{{ \App\Filament\Pages\EditMenuPage::getUrl(['record' => $menu->getKey()]) }}" class="scbd-manage-btn">
                                <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                                Manage
                            </a>

                            <div x-data="{ open: false }" class="scbd-more">
                                <button type="button" class="scbd-icon-btn" x-on:click="open = ! open" aria-label="More options for {{ $menu->name }}">
                                    <x-filament::icon icon="heroicon-o-ellipsis-horizontal" />
                                </button>

                                <div x-show="open" x-cloak x-on:click.outside="open = false" class="scbd-more-menu">
                                    <a href="{{ \App\Filament\Pages\EditMenuPage::getUrl(['record' => $menu->getKey()]) }}" class="scbd-more-item">
                                        <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                                        Edit Menu
                                    </a>

                                    <button
                                        type="button"
                                        class="scbd-more-item"
                                        x-on:click="navigator.clipboard.writeText(@js($menu->directive())); open = false"
                                    >
                                        <x-filament::icon icon="heroicon-o-document-duplicate" />
                                        Copy Directive
                                    </button>

                                    <button
                                        type="button"
                                        class="scbd-more-item scbd-more-item-danger"
                                        wire:click="deleteMenu('{{ $menu->id }}')"
                                        wire:confirm="Delete “{{ $menu->name }}” and all of its items? This cannot be undone."
                                        x-on:click="open = false"
                                    >
                                        <x-filament::icon icon="heroicon-o-trash" />
                                        Delete Menu
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-filament-panels::page>
