{{-- One row of the menu tree, recursing into its children. --}}
<li class="scbd-tree-item" data-menu-item="{{ $item->id }}" wire:key="item-{{ $item->id }}">
    <div @class([
        'scbd-tree-row',
        'scbd-tree-row-inactive' => ! $item->is_active,
        'scbd-tree-row-editing' => $this->editingId === (string) $item->id,
    ])>
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
            <button type="button" class="scbd-tree-action" wire:click="startEditing('{{ $item->id }}')" title="Edit this item">
                <x-filament::icon icon="heroicon-o-pencil" />
            </button>

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

    @if ($this->editingId === (string) $item->id)
        <div class="scbd-item-editor">
            @php($locales = $this->getLocales())

            <div class="scbd-field" x-data="{ locale: @js(array_key_first($locales)) }">
                <label>Label</label>

                {{-- Locale tabs rather than three stacked inputs: the quick-add
                     path stays one field, and translating stays one click away. --}}
                <div class="scbd-locale-tabs">
                    @foreach ($locales as $code => $name)
                        <button type="button" class="scbd-locale-tab"
                                x-on:click="locale = @js($code)"
                                x-bind:class="locale === @js($code) ? 'scbd-locale-tab-active' : ''">
                            {{ strtoupper($code) }}
                        </button>
                    @endforeach
                </div>

                @foreach ($locales as $code => $name)
                    <input type="text" class="scbd-input"
                           x-show="locale === @js($code)"
                           wire:model="editLabel.{{ $code }}"
                           placeholder="{{ $name }}{{ $code === \App\Models\MenuItem::FALLBACK_LOCALE ? '' : ' — falls back to English if left blank' }}">
                @endforeach
            </div>

            @if ($item->type === \App\Models\MenuItem::TYPE_CUSTOM)
                <div class="scbd-field">
                    <label for="edit-url-{{ $item->id }}">URL</label>
                    <input id="edit-url-{{ $item->id }}" type="text" class="scbd-input" wire:model="editUrl">
                </div>
            @else
                {{-- A linked item follows its record, so an editable URL here
                     would be a field that silently does nothing. --}}
                <p class="scbd-field-note">Links to {{ class_basename($item->linkable_type) }} — the URL follows that record.</p>
            @endif

            <div class="scbd-editor-row">
                <div class="scbd-field">
                    <label for="edit-target-{{ $item->id }}">Target</label>
                    <select id="edit-target-{{ $item->id }}" class="scbd-select" wire:model="editTarget">
                        <option value="_self">Same window</option>
                        <option value="_blank">New window</option>
                    </select>
                </div>

                <label class="scbd-toggle">
                    <input type="checkbox" wire:model="editActive">
                    <span class="scbd-toggle-track"><span class="scbd-toggle-thumb"></span></span>
                    Active
                </label>
            </div>

            <div class="scbd-editor-actions">
                <button type="button" class="scbd-editor-cancel" wire:click="cancelEditing">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                    Cancel
                </button>
                <button type="button" class="scbd-editor-save" wire:click="saveItem">Save</button>
            </div>
        </div>
    @endif

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
