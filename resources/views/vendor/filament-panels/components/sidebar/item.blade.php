{{--
    OVERRIDE of filament-panels::components.sidebar.item (Filament 5.7.4).

    Two deliberate departures from the vendor view, both requested:

    1. Child items render their icon. Upstream suppresses it for sub-grouped
       items unless the sidebar is collapsed to icon-only, showing a connecting
       bullet instead (see the `$subGrouped` guards below).
    2. Parents with children collapse. Upstream renders children permanently
       open whenever the parent has no URL, with no toggle.

    MAINTENANCE: this is a copy of vendor markup and will not receive upstream
    fixes. Re-diff it against the vendor file after any Filament upgrade.
--}}
@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();

    // Collapsed, a parent has no room to expand in place, so it opens a flyout
    // instead. The header shows the parent's label and the total of its
    // children's numeric badges — nothing is shown when that total is zero.
    $flyoutTotal = collect($childItems)
        ->map(fn ($childItem) => $childItem->getBadge())
        ->filter(fn ($childBadge) => is_numeric($childBadge))
        ->sum();
@endphp

<li
    @if ($childItems)
        x-data="{
            expanded: @js($active || $activeChildItems),
            flyout: false,
            flyoutTop: 0,
            flyoutLeft: 0,
            toggle() {
                if ($store.sidebar.isOpen) {
                    this.expanded = ! this.expanded

                    return
                }

                if (this.flyout) {
                    this.flyout = false

                    return
                }

                // Anchor to the rail's edge, not this item's — the item is
                // inset, so its right edge sits inside the rail.
                const rail = $el.closest('.fi-sidebar')
                const railRight = rail ? rail.getBoundingClientRect().right : $el.getBoundingClientRect().right

                this.flyoutTop = $el.getBoundingClientRect().top
                this.flyoutLeft = railRight + 8
                this.flyout = true

                // Items near the bottom would otherwise open off-screen.
                this.$nextTick(() => {
                    const panel = this.$refs.flyout
                    if (! panel) return

                    const overflow = panel.getBoundingClientRect().bottom - window.innerHeight + 8
                    if (overflow > 0) this.flyoutTop = Math.max(8, this.flyoutTop - overflow)
                })
            },
        }"
        x-effect="if ($store.sidebar.isOpen) flyout = false"
        {{-- The panel is teleported out of this <li>, so a click inside it
             reads as "outside" unless excluded explicitly. --}}
        @click.outside="$event.target.closest('.fi-sidebar-flyout') || (flyout = false)"
        @keydown.escape.window="flyout = false"
        {{-- Fixed positioning is measured once, so any scroll strands it. --}}
        @scroll.window.capture="flyout = false"
        @resize.window="flyout = false"
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
        ])
    }}
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        @if ($active)
            aria-current="page"
        @endif
        x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @if ($sidebarCollapsible && (! $subNavigation))
            x-bind:aria-label="$store.sidebar.isOpen ? null : @js(trim(strip_tags($slot->toHtml())))"
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn"
        @if ($childItems) @click.prevent="toggle()" @endif
    >
        {{-- OVERRIDE: sub-grouped items keep their icon instead of a bullet. --}}
        @if (filled($icon))
            {{
                \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
            }}
        @endif

        {{-- OVERRIDE: bullet only when there is no icon to show instead. --}}
        @if (blank($icon) && ($grouped || $subGrouped))
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border"
            >
                @if (! $first)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-first"
                    ></div>
                @endif

                @if (! $last)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-last"
                    ></div>
                @endif

                <div class="fi-sidebar-item-grouped-border-part"></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif
        @if ($childItems)
            {{-- OVERRIDE: collapsed, the chevron points at an expansion that
                 cannot happen — children open in a flyout instead. --}}
            <x-filament::icon
                icon="heroicon-m-chevron-down"
                class="fi-sidebar-item-chevron ms-auto h-5 w-5 transition-transform"
                ::class="expanded ? 'rotate-180' : ''"
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
            />
        @endif
    </a>

    {{-- OVERRIDE: flyout for a collapsed parent. Teleported to <body> because
         the sidebar clips its overflow and sits under a transformed ancestor —
         either one traps a position: fixed child, so in place the panel
         rendered with a real size and was simply never painted. Filament does
         not bundle Alpine's anchor plugin, hence the manual rect. --}}
    @if ($childItems && $sidebarCollapsible && (! $subNavigation))
        <template x-teleport="body">
        <div
            x-cloak
            x-ref="flyout"
            x-show="flyout"
            x-transition.opacity.100ms
            x-bind:style="`top: ${flyoutTop}px; left: ${flyoutLeft}px`"
            class="fi-sidebar-flyout"
            role="menu"
        >
            <div class="fi-sidebar-flyout-header">
                {{ $slot }}@if ($flyoutTotal > 0) <span class="fi-sidebar-flyout-count">({{ $flyoutTotal }})</span>@endif
            </div>

            <ul class="fi-sidebar-flyout-items">
                @foreach ($childItems as $childItem)
                    @php
                        $flyoutBadge = $childItem->getBadge();
                        $flyoutIcon = $childItem->getIcon();
                    @endphp

                    <li>
                        <a
                            {{ \Filament\Support\generate_href_html($childItem->getUrl(), $childItem->shouldOpenUrlInNewTab()) }}
                            @class([
                                'fi-sidebar-flyout-item',
                                'fi-active' => $childItem->isActive(),
                            ])
                            role="menuitem"
                        >
                            @if (filled($flyoutIcon))
                                {{
                                    \Filament\Support\generate_icon_html($flyoutIcon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                                    ]))->class(['fi-sidebar-flyout-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                                }}
                            @endif

                            <span class="fi-sidebar-flyout-item-label">{{ $childItem->getLabel() }}</span>

                            @if (filled($flyoutBadge))
                                <span class="fi-sidebar-flyout-item-badge">{{ $flyoutBadge }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        </template>
    @endif

    {{-- OVERRIDE: parents with children collapse. Upstream renders them always
         open. Starts expanded when the parent or one of its children is active,
         so the current page is never hidden behind a closed group. --}}
    @if ($childItems)
        <ul
            class="fi-sidebar-sub-group-items"
            x-show="expanded"
            x-collapse
        >
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
