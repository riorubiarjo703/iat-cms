<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\PageBuilder\BlockRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The block editor for a builder page.
 *
 * A separate screen rather than a field inside the page form: arranging blocks
 * and editing a block's fields are two different jobs, and nesting a second
 * Filament schema inside the resource form to do both would fight the outer
 * form's state.
 */
class BuildPage extends FilamentPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $slug = 'pages/{record}/build';

    protected string $view = 'filament.pages.build-page';

    protected static bool $shouldRegisterNavigation = false;

    /** The id, not the model — a public Eloquent property is serialised into
     *  the Livewire payload and handed back to mount() as JSON. */
    public int|string $recordId;

    protected ?Page $cachedRecord = null;

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    public ?string $editingId = null;

    /** @var array<string, mixed> The editing block's form state. */
    public ?array $blockData = [];

    public function mount(string|int $record): void
    {
        $this->recordId = $record;
        $this->blocks = $this->getRecord()->blocks();
    }

    public function getRecord(): Page
    {
        return $this->cachedRecord ??= Page::query()->findOrFail($this->recordId);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->t('title') ?: $this->getRecord()->slug;
    }

    public function getSubheading(): ?string
    {
        return 'Drag blocks to reorder. Click a block to edit its content.';
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            PageResource::getUrl('index') => 'Pages',
            $this->getTitle(),
        ];
    }

    public function registry(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    /** @return array<string, array<int, array{type: string, name: string, icon: string}>> */
    public function getPalette(): array
    {
        $palette = [];

        foreach ($this->registry()->byCategory() as $category => $classes) {
            foreach ($classes as $class) {
                $palette[$category][] = [
                    'type' => $class::type(),
                    'name' => $class::name(),
                    'icon' => $class::icon(),
                ];
            }
        }

        return $palette;
    }

    public function blockName(string $type): string
    {
        $class = $this->registry()->get($type);

        // An unknown type is still shown, labelled as such: hiding it would
        // make the editor silently drop a block on the next save.
        return $class ? $class::name() : "Unknown ({$type})";
    }

    public function blockIcon(string $type): string
    {
        $class = $this->registry()->get($type);

        return $class ? $class::icon() : 'heroicon-o-question-mark-circle';
    }

    // ── Composition ─────────────────────────────────────────────────────

    public function addBlock(string $type): void
    {
        $class = $this->registry()->get($type);

        if ($class === null) {
            return;
        }

        $this->blocks[] = [
            // Random is fine here — unlike the seeded homepage payload, an
            // interactively added block has no prior i18n keys to preserve.
            'id' => 'block_'.Str::lower(Str::random(10)),
            'type' => $type,
            'data' => $class::defaultData(),
            'children' => null,
        ];

        $this->persist();

        Notification::make()->title($class::name().' added')->success()->send();
    }

    public function removeBlock(string $id): void
    {
        $this->blocks = array_values(array_filter(
            $this->blocks,
            fn (array $block): bool => ($block['id'] ?? null) !== $id,
        ));

        if ($this->editingId === $id) {
            $this->cancelEditing();
        }

        $this->persist();
    }

    /** @param array<int, string> $order */
    public function saveOrder(array $order): void
    {
        $byId = collect($this->blocks)->keyBy('id');

        // Only ids this page already owns are honoured, and every existing
        // block must survive: a payload that omits one would delete it.
        $reordered = collect($order)
            ->map(fn (string $id) => $byId->get($id))
            ->filter()
            ->values();

        if ($reordered->count() !== count($this->blocks)) {
            return;
        }

        $this->blocks = $reordered->all();
        $this->persist();
    }

    // ── Editing one block ───────────────────────────────────────────────

    public function editBlock(string $id): void
    {
        $block = collect($this->blocks)->firstWhere('id', $id);

        if ($block === null || ! $this->registry()->has($block['type'] ?? '')) {
            return;
        }

        $this->editingId = $id;
        $this->form->fill(is_array($block['data'] ?? null) ? $block['data'] : []);
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->blockData = [];
    }

    public function saveBlock(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $state = $this->form->getState();

        $this->blocks = array_map(function (array $block) use ($state): array {
            if (($block['id'] ?? null) === $this->editingId) {
                $block['data'] = $state;
            }

            return $block;
        }, $this->blocks);

        $this->persist();
        $this->cancelEditing();

        Notification::make()->title('Block saved')->success()->send();
    }

    public function form(Schema $schema): Schema
    {
        $block = $this->editingId
            ? collect($this->blocks)->firstWhere('id', $this->editingId)
            : null;

        $class = $block ? $this->registry()->get($block['type'] ?? '') : null;

        return $schema
            ->components($class ? $class::schema() : [])
            ->statePath('blockData');
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $this->getRecord()->getPublicUrl())
                ->openUrlInNewTab(),

            Action::make('settings')
                ->label('Page settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn (): string => PageResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    private function persist(): void
    {
        $this->getRecord()->update(['builder_payload' => array_values($this->blocks)]);
    }
}
