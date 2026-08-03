{{-- One row of the menu tree, recursing into its children. --}}
<li class="scbd-tree-item" data-menu-item="{{ $item->id }}" wire:key="item-{{ $item->id }}">
    <div class="scbd-tree-row" @class(['scbd-tree-row-inactive' => ! $item->is_active])>
        <span class="scbd-tree-handle" data-menu-handle aria-hidden="true">
            <x-filament::icon icon="heroicon-o-ellipsis-vertical" />
        </span>

        @if ($item->children->isNotEmpty())
            <button type="button" class="scbd-tree-chevron" data-menu-toggle aria-label="Toggle children of {{ $item->t('label') }}">
                <x-filament::icon icon="heroicon-m-chevron-right" />
            </button>
        @else
            <span class="scbd-tree-chevron-spacer"></span>
        @endif

        {{-- Green when the item is live on the site, grey when hidden. --}}
        <span @class(['scbd-tree-dot', 'scbd-tree-dot-off' => ! $item->is_active])></span>

        <span class="scbd-tree-label">{{ $item->t('label') ?: 'Untitled' }}</span>

        <span class="scbd-tree-badges">
            @if ($item->is_cta)
                <span class="scbd-type-badge scbd-type-cta">CTA</span>
            @endif

            <span @class([
                'scbd-type-badge',
                'scbd-type-link' => $item->type === \App\Models\MenuItem::TYPE_CUSTOM,
                'scbd-type-page' => $item->type === \App\Models\MenuItem::TYPE_PAGE,
                'scbd-type-category' => $item->type === \App\Models\MenuItem::TYPE_CATEGORY,
            ])>
                @switch($item->type)
                    @case(\App\Models\MenuItem::TYPE_PAGE) Page @break
                    @case(\App\Models\MenuItem::TYPE_CATEGORY) Category @break
                    @default Link
                @endswitch
            </span>
        </span>

        <span class="scbd-tree-actions">
            <button type="button" class="scbd-tree-action" wire:click="toggleCta('{{ $item->id }}')"
                    title="{{ $item->is_cta ? 'Stop treating this as the call-to-action' : 'Make this the call-to-action button' }}">
                <x-filament::icon icon="heroicon-o-cursor-arrow-rays" />
            </button>

            <button type="button" class="scbd-tree-action" wire:click="toggleActive('{{ $item->id }}')"
                    title="{{ $item->is_active ? 'Hide from the site' : 'Show on the site' }}">
                <x-filament::icon :icon="$item->is_active ? 'heroicon-o-eye' : 'heroicon-o-eye-slash'" />
            </button>

            <button type="button" class="scbd-tree-action scbd-tree-action-danger"
                    wire:click="deleteItem('{{ $item->id }}')"
                    wire:confirm="Delete “{{ $item->t('label') ?: 'this item' }}”@if ($item->children->isNotEmpty()) and its {{ $item->children->count() }} nested {{ Str::plural('item', $item->children->count()) }}@endif?">
                <x-filament::icon icon="heroicon-o-trash" />
            </button>
        </span>
    </div>

    {{-- Always rendered, even when empty: an empty list is the drop target
         that makes a childless item able to become a parent. The empty class is
         set here rather than with CSS :empty, which does not match a list
         containing only Blade's whitespace. --}}
    <ul @class(['scbd-tree-children', 'scbd-tree-children-empty' => $item->children->isEmpty()]) data-menu-children>
        @foreach ($item->children as $child)
            @include('filament.pages.partials.menu-item', ['item' => $child, 'depth' => $depth + 1])
        @endforeach
    </ul>
</li>
