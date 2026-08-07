<x-filament-panels::page>
    <div class="scbd-builder">
        {{-- ── Palette ────────────────────────────────────────────────── --}}
        <aside class="scbd-card scbd-palette">
            <h3 class="scbd-card-title">Add a block</h3>

            @foreach ($this->getPalette() as $category => $items)
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
                            @foreach ($items as $item)
                                <button type="button" class="scbd-palette-item" wire:click="addBlock('{{ $item['type'] }}')">
                                    <span class="scbd-palette-icon">
                                        <x-filament::icon :icon="$item['icon']" />
                                    </span>
                                    <span>{{ $item['name'] }}</span>
                                    <x-filament::icon icon="heroicon-o-plus" class="scbd-palette-plus" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </aside>

        {{-- ── Canvas ─────────────────────────────────────────────────── --}}
        <section class="scbd-card scbd-canvas" x-data="blockList()">
            <div class="scbd-structure-head">
                <div>
                    <h3 class="scbd-card-title">Page structure</h3>
                    <p class="scbd-card-sub">{{ count($blocks) }} {{ Str::plural('block', count($blocks)) }}</p>
                </div>
            </div>

            @if (empty($blocks))
                <p class="scbd-empty">This page has no blocks yet. Add one from the panel on the left.</p>
            @endif

            <ul class="scbd-block-list" data-block-list>
                @foreach ($blocks as $block)
                    <li class="scbd-block" data-block="{{ $block['id'] }}" wire:key="block-{{ $block['id'] }}" wire:click="editBlock('{{ $block['id'] }}')">
                        <div @class(['scbd-block-row', 'scbd-block-row-editing' => $editingId === $block['id']])>
                            <span class="scbd-block-handle" data-block-handle aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-ellipsis-vertical" />
                            </span>

                            <span class="scbd-block-icon">
                                <x-filament::icon :icon="$this->blockIcon($block['type'])" />
                            </span>

                            <span class="scbd-block-label">{{ $this->blockName($block['type']) }}</span>

                            <span class="scbd-block-actions">
                                <button type="button" class="scbd-tree-action" wire:click="editBlock('{{ $block['id'] }}')" title="Edit this block">
                                    <x-filament::icon icon="heroicon-o-pencil" />
                                </button>
                                <button type="button" class="scbd-tree-action scbd-tree-action-danger"
                                        wire:click="removeBlock('{{ $block['id'] }}')"
                                        wire:confirm="Remove this {{ $this->blockName($block['type']) }} block?">
                                    <x-filament::icon icon="heroicon-o-trash" />
                                </button>
                            </span>
                        </div>

                        @if ($editingId === $block['id'])
                            <div class="scbd-block-editor">
                                {{ $this->form }}

                                <div class="scbd-editor-actions">
                                    <button type="button" class="scbd-editor-cancel" wire:click="cancelEditing">
                                        <x-filament::icon icon="heroicon-o-x-mark" />
                                        Cancel
                                    </button>
                                    <button type="button" class="scbd-editor-save" wire:click="saveBlock">Save block</button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-filament-panels::page>
