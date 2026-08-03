# Block Page Builder (Slice A1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the bespoke SCBD homepage and the GrapesJS page editor with one block-based builder, so every page — the homepage included — is assembled from the same reorderable, reusable blocks in a single editor.

**Architecture:** Four layers. A PHP **registry** defines nine block types (Filament schema + view name). A **content** layer adds `Page` and `ReusableBlock` models plus a `HasBlocks` concern that resolves library references and filters unknown types. A **presentation** layer renders one Blade partial per block type from a single renderer component. An **animation** layer mirrors the PHP registry in JS, giving each block a `gsap.context()` scoped to its own root element so block order becomes irrelevant.

**Tech Stack:** Laravel 13.23, Filament 5.7.4, PHP 8.4.16, PostgreSQL (sqlite `:memory:` for tests), PHPUnit 12.5.33, Vite 8, GSAP 3.12.5 + ScrollTrigger, Lenis 1.1.18.

**Spec:** `docs/superpowers/specs/2026-07-31-block-page-builder-design.md`

## Global Constraints

- The project **is** a git repository (`main`, pushed to `github.com/riorubiarjo703/iat-cms`). Commit after each task. Do **not** push — the controller handles remotes.
- **Never run destructive database commands**: no `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`. The live PostgreSQL holds real content and two real user accounts, and was wiped once during the previous build. Tests use sqlite `:memory:` via `phpunit.xml`; if a test run ever resolves to `pgsql`, STOP and report it.
- **Never run `npm run dev` and never create `public/hot`.** A stale hot-file previously pointed the site at a dead dev server and broke the homepage. Use `npm run build`.
- Tests are **PHPUnit 12.5.33**, not Pest. Classes extend `Tests\TestCase`, use `RefreshDatabase`, methods use the `test_` prefix. PHPUnit 12 ships no `AnnotationParser`, so doc-block metadata (`@dataProvider`, `@test`, `@depends`, `@group`) is **silently ignored** — always use attributes from `PHPUnit\Framework\Attributes\*`.
- **Trait constants cannot be accessed directly** (PHP 8.4): `HasTranslatableFields::FALLBACK_LOCALE` raises `Error: Cannot access trait constant ... directly`. Read it through a composing class — `SiteSetting::FALLBACK_LOCALE`.
- **Filament navigation groups and their items cannot both carry icons** — setting `->icon()` on a `NavigationGroup` whose items also have icons throws at render. Icons go on items only.
- Filament 5 namespaces: schemas/layout in `Filament\Schemas\*`; fields in `Filament\Forms\Components\*`; **actions in `Filament\Actions\*`**, never `Filament\Tables\Actions\*`. Resource signatures are `public static function form(Schema $schema): Schema` and `public static function table(Table $table): Table`. Table methods are `recordActions()` and `toolbarActions()`.
- `$view` on a Filament `Page` is a **non-static** `protected string`.
- Filament forms materialise **every locale key** for translatable fields, so any `assertFormSet` on one must expect the full `['en' => …, 'id' => null, 'cn' => null]` map.
- Locales are exactly `en`, `id`, `cn`; `en` is the fallback for every translatable field and is `required` on every translatable form field.
- **The block JSON shape is dictated by Filament, not chosen by us.** `Builder::mutateDehydratedStateUsing` does `array_values($state)` on save and `hydrateItems()` regenerates UUID keys on load (`vendor/filament/forms/src/Components/Builder.php:141-158`). The persisted column is a plain indexed array where only `type` and `data` survive. There is **no stable per-block id** — blocks are addressed by render-time array index.
- Filament's Builder already discards items whose `type` is unregistered (`Builder.php:949`), but `renderableBlocks()` must repeat that filter independently because it reads the column directly and never passes through the form.

---

## File Structure

```
app/Blocks/
  Block.php                      abstract base: type/label/icon/schema/view/defaults
  BlockRegistry.php              singleton; all/get/has/toBuilderBlocks
  Types/HeroBlock.php            one file per block type
  Types/TextImageBlock.php
  Types/StatsBlock.php
  Types/MarqueeBlock.php
  Types/HorizontalScrollBlock.php
  Types/StackedCardsBlock.php
  Types/PostListBlock.php
  Types/CtaBlock.php
  Types/ContactBlock.php
  Types/ReusableBlock.php        resolves to another block; schema is one Select

app/Concerns/HasBlocks.php       casts, resolves refs, filters unknown types
app/Models/Page.php
app/Models/ReusableBlock.php
app/Support/PageData.php         replaces HomepageData; DTO + i18n payload
app/Http/Controllers/PageController.php

app/Filament/Resources/Pages/PageResource.php + Pages/
app/Filament/Resources/ReusableBlocks/ReusableBlockResource.php + Pages/

resources/views/components/block-renderer.blade.php
resources/views/blocks/*.blade.php          one per type (9)
resources/views/page.blade.php              layout + renderer

resources/js/blocks/index.js                type -> initialiser map
resources/js/blocks/*.js                    one per effect-bearing type
resources/js/scbd/index.js                  bootstrap: page-level concerns + block dispatch

database/migrations/2026_07_31_1000*_*.php  pages, reusable_blocks
database/seeders/HomepageBlocksSeeder.php   the homepage rebuilt as blocks
```

---

### Task 1: `Page` and `ReusableBlock` models with `HasBlocks`

**Files:**
- Create: `database/migrations/2026_07_31_100000_create_pages_table.php`
- Create: `database/migrations/2026_07_31_100100_create_reusable_blocks_table.php`
- Create: `app/Concerns/HasBlocks.php`
- Create: `app/Models/Page.php`
- Create: `app/Models/ReusableBlock.php`
- Test: `tests/Unit/Models/PageTest.php`
- Test: `tests/Unit/Concerns/HasBlocksTest.php`

**Interfaces:**
- Consumes: `App\Concerns\HasTranslatableFields` — `t($key, $locale)` with per-key `en` fallback, `translations($key)`, `getCasts()` merging.
- Produces:
  - `App\Models\Page` — `TRANSLATABLE = ['title', 'meta_title', 'meta_description']`; columns `slug`, `blocks`, `status`, `is_homepage`, `og_image`. Scopes `published()` and `homepage()`. `Page::homepage(): ?self`.
  - `App\Models\ReusableBlock` — columns `name`, `type`, `data`; `$casts = ['data' => 'array']`.
  - `App\Concerns\HasBlocks` — `blocks` cast to `array`; `renderableBlocks(): array` returning entries shaped `['type' => string, 'data' => array, 'index' => int]`; `blockTypes(): array`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Concerns/HasBlocksTest.php`:

```php
<?php

namespace Tests\Unit\Concerns;

use App\Models\Page;
use App\Models\ReusableBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks): Page
    {
        return Page::create([
            'title' => ['en' => 'Test'],
            'slug' => 'test-'.uniqid(),
            'blocks' => $blocks,
            'status' => 'published',
        ]);
    }

    public function test_blocks_round_trip_as_an_indexed_array(): void
    {
        $page = $this->page([
            ['type' => 'hero', 'data' => ['heading' => ['en' => 'Hi']]],
            ['type' => 'cta', 'data' => []],
        ]);

        $this->assertSame(['hero', 'cta'], array_column($page->fresh()->blocks, 'type'));
    }

    public function test_renderable_blocks_carry_their_render_time_index(): void
    {
        $blocks = $this->page([
            ['type' => 'hero', 'data' => []],
            ['type' => 'cta', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame([0, 1], array_column($blocks, 'index'));
    }

    public function test_it_filters_out_types_absent_from_the_registry(): void
    {
        $blocks = $this->page([
            ['type' => 'hero', 'data' => []],
            ['type' => 'no-such-block', 'data' => []],
            ['type' => 'cta', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame(['hero', 'cta'], array_column($blocks, 'type'));
    }

    public function test_indexes_are_contiguous_after_filtering(): void
    {
        // A dropped block must not leave a hole — the i18n payload is keyed by
        // this index, so a gap would desynchronise it from the rendered DOM.
        $blocks = $this->page([
            ['type' => 'no-such-block', 'data' => []],
            ['type' => 'hero', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame([0], array_column($blocks, 'index'));
    }

    public function test_a_reusable_entry_resolves_to_the_library_type_and_data(): void
    {
        $lib = ReusableBlock::create([
            'name' => 'Facilities',
            'type' => 'stacked-cards',
            'data' => ['heading' => ['en' => 'Services']],
        ]);

        $blocks = $this->page([['type' => 'reusable', 'data' => ['ref' => $lib->id]]])->renderableBlocks();

        $this->assertSame('stacked-cards', $blocks[0]['type']);
        $this->assertSame(['en' => 'Services'], $blocks[0]['data']['heading']);
    }

    public function test_a_dangling_reference_is_dropped(): void
    {
        $blocks = $this->page([
            ['type' => 'reusable', 'data' => ['ref' => 999999]],
            ['type' => 'cta', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame(['cta'], array_column($blocks, 'type'));
    }

    public function test_reference_resolution_costs_one_query_regardless_of_count(): void
    {
        $lib = ReusableBlock::create(['name' => 'X', 'type' => 'cta', 'data' => []]);
        $page = $this->page(array_fill(0, 10, ['type' => 'reusable', 'data' => ['ref' => $lib->id]]));

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        $page->renderableBlocks();
        $this->assertCount(1, \DB::getQueryLog());
    }

    public function test_block_types_lists_distinct_types(): void
    {
        $page = $this->page([
            ['type' => 'hero', 'data' => []],
            ['type' => 'cta', 'data' => []],
            ['type' => 'hero', 'data' => []],
        ]);

        $this->assertSame(['hero', 'cta'], $page->blockTypes());
    }
}
```

Create `tests/Unit/Models/PageTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_the_flagged_row(): void
    {
        Page::create(['title' => ['en' => 'About'], 'slug' => 'about', 'status' => 'published']);
        $home = Page::create(['title' => ['en' => 'Home'], 'slug' => 'home', 'status' => 'published', 'is_homepage' => true]);

        $this->assertSame($home->id, Page::homepage()->id);
    }

    public function test_homepage_returns_null_when_none_is_flagged(): void
    {
        Page::create(['title' => ['en' => 'About'], 'slug' => 'about', 'status' => 'published']);

        $this->assertNull(Page::homepage());
    }

    public function test_only_one_row_may_be_flagged_as_homepage(): void
    {
        Page::create(['title' => ['en' => 'A'], 'slug' => 'a', 'status' => 'published', 'is_homepage' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Page::create(['title' => ['en' => 'B'], 'slug' => 'b', 'status' => 'published', 'is_homepage' => true]);
    }

    public function test_published_scope_excludes_drafts(): void
    {
        Page::create(['title' => ['en' => 'Live'], 'slug' => 'live', 'status' => 'published']);
        Page::create(['title' => ['en' => 'Draft'], 'slug' => 'draft', 'status' => 'draft']);

        $this->assertSame(['live'], Page::query()->published()->pluck('slug')->all());
    }

    public function test_title_and_meta_are_translatable_with_english_fallback(): void
    {
        $page = Page::create([
            'title' => ['en' => 'Home', 'id' => 'Beranda'],
            'meta_title' => ['en' => 'Home | SCBD'],
            'slug' => 'home',
            'status' => 'published',
        ]);

        $this->assertSame('Beranda', $page->t('title', 'id'));
        $this->assertSame('Home', $page->t('title', 'cn'));
        $this->assertSame('Home | SCBD', $page->t('meta_title', 'cn'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Concerns/HasBlocksTest.php tests/Unit/Models/PageTest.php`
Expected: FAIL — `Class "App\Models\Page" not found`.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_07_31_100000_create_pages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->string('slug')->unique();
            $table->json('blocks')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_homepage')->default(false);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->timestamps();

            $table->index(['status', 'slug']);
        });

        // Exactly one homepage. A partial index expresses "unique among true rows",
        // which a plain unique index cannot. sqlite and Postgres share this syntax;
        // guarded so the test driver does not choke on an unsupported statement.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX pages_single_homepage ON pages (is_homepage) WHERE is_homepage = true');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
```

Create `database/migrations/2026_07_31_100100_create_reusable_blocks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reusable_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reusable_blocks');
    }
};
```

- [ ] **Step 4: Write the `HasBlocks` concern**

Create `app/Concerns/HasBlocks.php`:

```php
<?php

namespace App\Concerns;

use App\Blocks\BlockRegistry;
use App\Models\ReusableBlock;
use Illuminate\Support\Facades\Log;

/**
 * Block storage and resolution.
 *
 * The persisted shape is dictated by Filament's Builder: a plain indexed array
 * of ['type' => string, 'data' => array]. Filament strips its UUID keys on save
 * and regenerates them on load, so there is no stable per-block identifier —
 * blocks are addressed by render-time index.
 */
trait HasBlocks
{
    public function getCasts(): array
    {
        return array_merge(parent::getCasts(), ['blocks' => 'array']);
    }

    /**
     * @return array<int, string>
     */
    public function blockTypes(): array
    {
        return array_values(array_unique(array_column($this->blocks ?? [], 'type')));
    }

    /**
     * Render-ready blocks: library references resolved, unknown types dropped,
     * indexes renumbered contiguously.
     *
     * Indexes must be contiguous because the i18n payload is keyed by them; a
     * gap would desynchronise the payload from the rendered DOM.
     *
     * @return array<int, array{type: string, data: array, index: int}>
     */
    public function renderableBlocks(): array
    {
        $stored = $this->blocks ?? [];
        $registry = app(BlockRegistry::class);

        // One query for every reference on the page, not one per reference.
        $refs = collect($stored)
            ->filter(fn (array $b): bool => ($b['type'] ?? null) === 'reusable')
            ->pluck('data.ref')
            ->filter()
            ->unique();

        $library = $refs->isEmpty()
            ? collect()
            : ReusableBlock::query()->whereIn('id', $refs)->get()->keyBy('id');

        $out = [];

        foreach ($stored as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if ($type === 'reusable') {
                $entry = $library->get($data['ref'] ?? null);

                if (! $entry) {
                    Log::warning('Block references a missing library entry.', [
                        'model' => static::class,
                        'id' => $this->getKey(),
                        'ref' => $data['ref'] ?? null,
                    ]);

                    continue;
                }

                $type = $entry->type;
                $data = $entry->data ?? [];
            }

            if (! $registry->has($type)) {
                Log::warning('Block type is not registered.', [
                    'model' => static::class,
                    'id' => $this->getKey(),
                    'type' => $type,
                ]);

                continue;
            }

            $out[] = ['type' => $type, 'data' => $data, 'index' => count($out)];
        }

        return $out;
    }
}
```

- [ ] **Step 5: Write the models**

Create `app/Models/Page.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasBlocks;
use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasBlocks;
    use HasTranslatableFields;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['title', 'meta_title', 'meta_description'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_homepage' => 'boolean',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public static function homepage(): ?self
    {
        return static::query()->published()->where('is_homepage', true)->first();
    }
}
```

Create `app/Models/ReusableBlock.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReusableBlock extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];
}
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Unit/Concerns/HasBlocksTest.php tests/Unit/Models/PageTest.php`
Expected: FAIL — `BlockRegistry` does not exist yet. That is correct: Task 2 supplies it. Note which tests fail for that reason and move on; do **not** stub the registry here.

- [ ] **Step 7: Migrate and commit**

```bash
php artisan migrate
git add app/Concerns/HasBlocks.php app/Models/Page.php app/Models/ReusableBlock.php database/migrations tests/Unit
git commit -m "feat: add Page and ReusableBlock models with HasBlocks"
```

---

### Task 2: Block registry, base class, and the `hero` block

This task establishes the pattern every other block follows. Get it right and Tasks 3–4 are transcription.

**Files:**
- Create: `app/Blocks/Block.php`
- Create: `app/Blocks/BlockRegistry.php`
- Create: `app/Blocks/Types/HeroBlock.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Blocks/BlockRegistryTest.php`

**Interfaces:**
- Consumes: `App\Filament\Support\LocaleTabs::make(Closure $components, ?string $label = null): Tabs` and `LocaleTabs::isFallback(string $locale): bool` (true for `'en'`).
- Produces:
  - `App\Blocks\Block` — abstract, with `type()`, `label()`, `icon()`, `schema()` abstract-or-concrete as below.
  - `App\Blocks\BlockRegistry` — bound as a singleton; `register(string $class): static`, `all(): array` (type => class), `get(string $type): ?string`, `has(string $type): bool`, `toBuilderBlocks(): array`.
  - `App\Blocks\Types\HeroBlock` — type `hero`, view `blocks.hero`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Blocks/BlockRegistryTest.php`:

```php
<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockRegistry;
use App\Blocks\Types\HeroBlock;
use Filament\Forms\Components\Builder\Block as FilamentBlock;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    public function test_it_is_bound_as_a_singleton(): void
    {
        $this->assertSame(app(BlockRegistry::class), app(BlockRegistry::class));
    }

    public function test_the_hero_block_is_registered_by_default(): void
    {
        $this->assertTrue(app(BlockRegistry::class)->has('hero'));
        $this->assertSame(HeroBlock::class, app(BlockRegistry::class)->get('hero'));
    }

    public function test_an_unregistered_type_is_absent(): void
    {
        $this->assertFalse(app(BlockRegistry::class)->has('no-such-block'));
        $this->assertNull(app(BlockRegistry::class)->get('no-such-block'));
    }

    public function test_every_registered_block_declares_a_view_that_exists(): void
    {
        foreach (app(BlockRegistry::class)->all() as $type => $class) {
            $this->assertTrue(
                view()->exists($class::view()),
                "Block [{$type}] declares view [{$class::view()}] which does not exist",
            );
        }
    }

    public function test_every_registered_block_has_a_schema_that_builds(): void
    {
        foreach (app(BlockRegistry::class)->all() as $type => $class) {
            $this->assertIsArray($class::schema(), "Block [{$type}] schema did not build");
            $this->assertNotEmpty($class::schema(), "Block [{$type}] schema is empty");
        }
    }

    public function test_to_builder_blocks_returns_filament_blocks_keyed_correctly(): void
    {
        $blocks = app(BlockRegistry::class)->toBuilderBlocks();

        $this->assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            $this->assertInstanceOf(FilamentBlock::class, $block);
        }

        $names = array_map(fn (FilamentBlock $b) => $b->getName(), $blocks);
        $this->assertContains('hero', $names);
    }

    public function test_registry_types_match_the_javascript_registry(): void
    {
        // The PHP and JS registries are mirrors. If they drift, a block renders
        // with no animation and nothing errors.
        $js = file_get_contents(base_path('resources/js/blocks/index.js'));
        preg_match_all("/^\s{2}'([a-z-]+)':/m", $js, $m);

        $phpTypes = array_keys(app(BlockRegistry::class)->all());
        sort($phpTypes);
        $jsTypes = $m[1];
        sort($jsTypes);

        $this->assertSame($phpTypes, $jsTypes, 'PHP and JS block registries have drifted apart');
    }
}
```

Note the last test will fail until Task 6 creates `resources/js/blocks/index.js`. That is expected and is the point — it holds the two registries together for the rest of the plan.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Blocks/BlockRegistryTest.php`
Expected: FAIL — `Class "App\Blocks\BlockRegistry" not found`.

- [ ] **Step 3: Write the abstract base**

Create `app/Blocks/Block.php`:

```php
<?php

namespace App\Blocks;

use Filament\Forms\Components\Builder\Block as FilamentBlock;

abstract class Block
{
    /** Stable identifier stored in the JSON column and emitted as data-block. */
    abstract public static function type(): string;

    /** Human label shown in the Builder's block picker. */
    abstract public static function label(): string;

    /** Heroicon name shown beside the label. */
    abstract public static function icon(): string;

    /**
     * Filament components for this block's editing form.
     *
     * @return array<int, mixed>
     */
    abstract public static function schema(): array;

    /** Blade view rendering this block. */
    public static function view(): string
    {
        return 'blocks.'.static::type();
    }

    /**
     * Seed data for a newly added instance.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [];
    }

    public static function toBuilderBlock(): FilamentBlock
    {
        return FilamentBlock::make(static::type())
            ->label(static::label())
            ->icon(static::icon())
            ->schema(static::schema());
    }
}
```

- [ ] **Step 4: Write the registry**

Create `app/Blocks/BlockRegistry.php`:

```php
<?php

namespace App\Blocks;

use Filament\Forms\Components\Builder\Block as FilamentBlock;

/**
 * The single place block types are declared.
 *
 * The admin form, the renderer and validation all read from here, so adding a
 * block means touching one file. Its JS mirror lives at resources/js/blocks/index.js
 * and a test asserts the two key sets match.
 */
class BlockRegistry
{
    /** @var array<string, class-string<Block>> */
    private array $blocks = [];

    /**
     * @param  class-string<Block>  $class
     */
    public function register(string $class): static
    {
        $this->blocks[$class::type()] = $class;

        return $this;
    }

    /**
     * @return array<string, class-string<Block>>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * @return class-string<Block>|null
     */
    public function get(string $type): ?string
    {
        return $this->blocks[$type] ?? null;
    }

    public function has(?string $type): bool
    {
        return $type !== null && array_key_exists($type, $this->blocks);
    }

    /**
     * @return array<int, FilamentBlock>
     */
    public function toBuilderBlocks(): array
    {
        return array_values(array_map(
            fn (string $class): FilamentBlock => $class::toBuilderBlock(),
            $this->blocks,
        ));
    }
}
```

- [ ] **Step 5: Write the hero block**

Create `app/Blocks/Types/HeroBlock.php`:

```php
<?php

namespace App\Blocks\Types;

use App\Blocks\Block;
use App\Filament\Support\LocaleTabs;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class HeroBlock extends Block
{
    public static function type(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'Hero';
    }

    public static function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                Textarea::make("heading.$locale")
                    ->label('Heading')
                    ->rows(3)
                    ->helperText('Each new line becomes one animated line of the heading.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("sub.$locale")
                    ->label('Paragraph')
                    ->rows(3)
                    ->required(LocaleTabs::isFallback($locale)),
                TextInput::make("cta_label.$locale")->label('Button label'),
            ]),
            FileUpload::make('image')
                ->label('Hero image')
                ->image()->disk('public')->directory('uploads/pages')
                ->visibility('public')->maxSize(5120),
            TextInput::make('cta_url')->label('Button URL')->maxLength(255),
        ];
    }
}
```

- [ ] **Step 6: Bind the registry**

In `app/Providers/AppServiceProvider.php`, read the file first, then add to `register()`:

```php
$this->app->singleton(\App\Blocks\BlockRegistry::class, function (): \App\Blocks\BlockRegistry {
    return (new \App\Blocks\BlockRegistry)
        ->register(\App\Blocks\Types\HeroBlock::class);
});
```

Later tasks append `->register(...)` calls here. This is the only place block classes are enumerated.

- [ ] **Step 7: Run the tests**

Run: `php artisan test tests/Unit/Blocks/BlockRegistryTest.php`
Expected: all pass except `test_every_registered_block_declares_a_view_that_exists` (Task 5 adds views) and `test_registry_types_match_the_javascript_registry` (Task 6 adds the JS mirror). Confirm those two are the only failures, and that they fail for the stated reason rather than some other error.

- [ ] **Step 8: Commit**

```bash
git add app/Blocks app/Providers/AppServiceProvider.php tests/Unit/Blocks
git commit -m "feat: add block registry, base class and hero block"
```

---

### Task 3: The seven remaining content blocks

Every block follows the Task 2 pattern exactly: extend `App\Blocks\Block`, implement the four abstract methods, register in `AppServiceProvider`. The `reusable` block is deliberately excluded — it needs reference resolution and gets its own task.

**Files:**
- Create: `app/Blocks/Types/{TextImageBlock,StatsBlock,MarqueeBlock,HorizontalScrollBlock,StackedCardsBlock,PostListBlock,CtaBlock,ContactBlock}.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Blocks/BlockSchemasTest.php`

**Interfaces:**
- Consumes: `App\Blocks\Block`, `App\Filament\Support\LocaleTabs`, `App\Enums\StatFormat` (`options(): array` returning `['plain' => 'Plain (45)', 'thousands' => 'Thousands separated (1,200)']`).
- Produces: eight block classes with the types `text-image`, `stats`, `marquee`, `horizontal-scroll`, `stacked-cards`, `post-list`, `cta`, `contact`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Blocks/BlockSchemasTest.php`:

```php
<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlockSchemasTest extends TestCase
{
    public static function expectedTypes(): array
    {
        return [
            ['hero'], ['text-image'], ['stats'], ['marquee'],
            ['horizontal-scroll'], ['stacked-cards'], ['post-list'],
            ['cta'], ['contact'],
        ];
    }

    #[DataProvider('expectedTypes')]
    public function test_the_block_is_registered(string $type): void
    {
        $this->assertTrue(app(BlockRegistry::class)->has($type), "Block [{$type}] is not registered");
    }

    #[DataProvider('expectedTypes')]
    public function test_the_block_declares_a_label_and_icon(string $type): void
    {
        $class = app(BlockRegistry::class)->get($type);

        $this->assertNotSame('', $class::label());
        $this->assertStringStartsWith('heroicon-', $class::icon());
    }

    public function test_exactly_nine_content_blocks_are_registered(): void
    {
        // 'reusable' arrives in Task 4 and is counted separately there.
        $this->assertCount(9, app(BlockRegistry::class)->all());
    }

    public function test_the_stats_block_offers_both_number_formats(): void
    {
        $schema = app(BlockRegistry::class)->get('stats')::schema();

        $this->assertNotEmpty($schema, 'Stats schema did not build');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Blocks/BlockSchemasTest.php`
Expected: FAIL — only `hero` is registered.

- [ ] **Step 3: Write the eight block classes**

Each file follows the `HeroBlock` shape. The four methods per block:

**`TextImageBlock`** — type `text-image`, label `Text + Image`, icon `heroicon-o-photo`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2)
                ->required(LocaleTabs::isFallback($locale)),
            Textarea::make("body.$locale")->label('Body')->rows(4)
                ->required(LocaleTabs::isFallback($locale)),
            TextInput::make("cta_label.$locale")->label('Button label'),
        ]),
        FileUpload::make('image')->image()->disk('public')->directory('uploads/pages')
            ->visibility('public')->maxSize(5120),
        TextInput::make('cta_url')->label('Button URL')->maxLength(255),
        Toggle::make('reversed')->label('Image on the left')->default(false),
    ];
}
```

**`StatsBlock`** — type `stats`, label `Stats`, icon `heroicon-o-chart-bar`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2),
        ]),
        Repeater::make('items')
            ->label('Statistics')
            ->schema([
                LocaleTabs::make(fn (string $locale): array => [
                    TextInput::make("label.$locale")->label('Label')
                        ->required(LocaleTabs::isFallback($locale)),
                ]),
                TextInput::make('value')->label('Counts up to')->numeric()->required(),
                TextInput::make('suffix')->label('Suffix')
                    ->helperText('Appended after the number, e.g. /7 or %.')->maxLength(16),
                Select::make('format')->label('Number format')
                    ->options(StatFormat::options())
                    ->default(StatFormat::Thousands->value)
                    ->required()
                    ->helperText('Use Plain for years so 1987 is not rendered as 1,987.'),
            ])
            ->defaultItems(3)
            ->reorderable(),
    ];
}
```

**`MarqueeBlock`** — type `marquee`, label `Marquee`, icon `heroicon-o-arrows-right-left`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            TextInput::make("text.$locale")->label('Scrolling text')
                ->helperText('Repeated automatically to fill the strip.')
                ->required(LocaleTabs::isFallback($locale)),
        ]),
        TextInput::make('duration')->label('Seconds per loop')->numeric()->default(26),
    ];
}
```

**`HorizontalScrollBlock`** — type `horizontal-scroll`, label `Horizontal Scroll`, icon `heroicon-o-arrows-pointing-out`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2)
                ->helperText('Each new line becomes one animated line.')
                ->required(LocaleTabs::isFallback($locale)),
            Textarea::make("body.$locale")->label('Intro paragraph')->rows(3),
        ]),
        Repeater::make('items')
            ->label('Panels')
            ->schema([
                LocaleTabs::make(fn (string $locale): array => [
                    TextInput::make("title.$locale")->label('Title')
                        ->required(LocaleTabs::isFallback($locale)),
                    TextInput::make("caption.$locale")->label('Caption'),
                ]),
                FileUpload::make('image')->image()->disk('public')
                    ->directory('uploads/pages')->visibility('public')->maxSize(5120),
            ])
            ->defaultItems(3)
            ->reorderable(),
    ];
}
```

**`StackedCardsBlock`** — type `stacked-cards`, label `Stacked Cards`, icon `heroicon-o-square-3-stack-3d`. Identical to `HorizontalScrollBlock` except the repeater's per-item fields are `title` (TextInput) and `body` (Textarea, `rows(4)`) instead of `title` and `caption`, and its label is `Cards`.

**`PostListBlock`** — type `post-list`, label `Post List`, icon `heroicon-o-newspaper`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2)
                ->required(LocaleTabs::isFallback($locale)),
            TextInput::make("cta_label.$locale")->label('Link label'),
        ]),
        TextInput::make('count')->label('How many posts')->numeric()->default(3)->required(),
    ];
}
```

**`CtaBlock`** — type `cta`, label `Call to action`, icon `heroicon-o-megaphone`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2)
                ->required(LocaleTabs::isFallback($locale)),
            TextInput::make("button_label.$locale")->label('Button label')
                ->required(LocaleTabs::isFallback($locale)),
        ]),
        TextInput::make('url')->label('Button URL')->required()->maxLength(255),
    ];
}
```

**`ContactBlock`** — type `contact`, label `Contact`, icon `heroicon-o-envelope`:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale): array => [
            Textarea::make("heading.$locale")->label('Heading')->rows(2)
                ->helperText('Each new line becomes one animated line.')
                ->required(LocaleTabs::isFallback($locale)),
        ]),
        TextInput::make('email')->label('Email')->email()->maxLength(255),
        TextInput::make('phone')->label('Phone')->maxLength(255),
        Textarea::make('address')->label('Address')->rows(3)
            ->helperText('Each new line becomes one line in the address block.'),
    ];
}
```

Imports needed across these files: `Filament\Forms\Components\{FileUpload, Repeater, Select, Textarea, TextInput, Toggle}`, `App\Enums\StatFormat`, `App\Filament\Support\LocaleTabs`, `App\Blocks\Block`.

- [ ] **Step 4: Register all eight**

Extend the singleton closure in `app/Providers/AppServiceProvider.php` so it reads:

```php
return (new \App\Blocks\BlockRegistry)
    ->register(\App\Blocks\Types\HeroBlock::class)
    ->register(\App\Blocks\Types\TextImageBlock::class)
    ->register(\App\Blocks\Types\StatsBlock::class)
    ->register(\App\Blocks\Types\MarqueeBlock::class)
    ->register(\App\Blocks\Types\HorizontalScrollBlock::class)
    ->register(\App\Blocks\Types\StackedCardsBlock::class)
    ->register(\App\Blocks\Types\PostListBlock::class)
    ->register(\App\Blocks\Types\CtaBlock::class)
    ->register(\App\Blocks\Types\ContactBlock::class);
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Unit/Blocks/BlockSchemasTest.php`
Expected: PASS — 19 tests (9 + 9 data-provider cases plus the count and stats checks).

**Prove the count test has teeth:** comment out one `->register(...)` line, confirm both that block's provider cases and `test_exactly_nine_content_blocks_are_registered` fail, then restore. Report the observed numbers.

- [ ] **Step 6: Commit**

```bash
git add app/Blocks/Types app/Providers/AppServiceProvider.php tests/Unit/Blocks
git commit -m "feat: add the eight remaining content blocks"
```

---


---

### Task 4: The `reusable` block and library resolution

**Files:**
- Create: `app/Blocks/Types/ReusableBlockType.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Blocks/ReusableBlockTest.php`

**Interfaces:**
- Consumes: `App\Blocks\Block`; `App\Models\ReusableBlock` (columns `name`, `type`, `data`); `App\Concerns\HasBlocks::renderableBlocks()` which already resolves `type === 'reusable'` entries against the library (written in Task 1).
- Produces: `App\Blocks\Types\ReusableBlockType` — type `reusable`, label `From library`, icon `heroicon-o-rectangle-stack`, schema a single `Select` bound to `data.ref`.

**Naming note:** the class is `ReusableBlockType`, not `ReusableBlock`, because `App\Models\ReusableBlock` already exists. Importing both into one file otherwise collides.

**Why this is a block type rather than a flag:** Filament persists only `type` and `data`, so a `ref` cannot sit beside them. See the Global Constraints.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Blocks/ReusableBlockTest.php`:

```php
<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockRegistry;
use App\Blocks\Types\ReusableBlockType;
use App\Models\Page;
use App\Models\ReusableBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReusableBlockTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks): Page
    {
        return Page::create([
            'title' => ['en' => 'T'],
            'slug' => 'p-'.uniqid(),
            'blocks' => $blocks,
            'status' => 'published',
        ]);
    }

    public function test_it_is_registered(): void
    {
        $this->assertTrue(app(BlockRegistry::class)->has('reusable'));
        $this->assertSame(ReusableBlockType::class, app(BlockRegistry::class)->get('reusable'));
    }

    public function test_ten_block_types_are_registered_in_total(): void
    {
        $this->assertCount(10, app(BlockRegistry::class)->all());
    }

    public function test_a_reference_renders_the_library_entrys_type_and_data(): void
    {
        $lib = ReusableBlock::create([
            'name' => 'Facilities',
            'type' => 'stacked-cards',
            'data' => ['heading' => ['en' => 'Services']],
        ]);

        $blocks = $this->page([['type' => 'reusable', 'data' => ['ref' => $lib->id]]])->renderableBlocks();

        $this->assertSame('stacked-cards', $blocks[0]['type']);
        $this->assertSame(['en' => 'Services'], $blocks[0]['data']['heading']);
    }

    public function test_editing_the_library_entry_changes_every_referencing_page(): void
    {
        $lib = ReusableBlock::create([
            'name' => 'CTA', 'type' => 'cta', 'data' => ['heading' => ['en' => 'Before']],
        ]);
        $a = $this->page([['type' => 'reusable', 'data' => ['ref' => $lib->id]]]);
        $b = $this->page([['type' => 'reusable', 'data' => ['ref' => $lib->id]]]);

        $lib->update(['data' => ['heading' => ['en' => 'After']]]);

        $this->assertSame('After', $a->fresh()->renderableBlocks()[0]['data']['heading']['en']);
        $this->assertSame('After', $b->fresh()->renderableBlocks()[0]['data']['heading']['en']);
    }

    public function test_a_detached_copy_no_longer_tracks_the_library(): void
    {
        $lib = ReusableBlock::create([
            'name' => 'CTA', 'type' => 'cta', 'data' => ['heading' => ['en' => 'Before']],
        ]);
        // Detaching replaces the reference with a plain block carrying a copy.
        $page = $this->page([['type' => 'cta', 'data' => ['heading' => ['en' => 'Before']]]]);

        $lib->update(['data' => ['heading' => ['en' => 'After']]]);

        $this->assertSame('Before', $page->fresh()->renderableBlocks()[0]['data']['heading']['en']);
    }

    public function test_a_reference_to_a_library_entry_of_an_unregistered_type_is_dropped(): void
    {
        $lib = ReusableBlock::create(['name' => 'Stale', 'type' => 'no-such-block', 'data' => []]);

        $blocks = $this->page([
            ['type' => 'reusable', 'data' => ['ref' => $lib->id]],
            ['type' => 'cta', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame(['cta'], array_column($blocks, 'type'));
    }

    public function test_a_reference_with_no_ref_value_is_dropped(): void
    {
        $blocks = $this->page([
            ['type' => 'reusable', 'data' => []],
            ['type' => 'cta', 'data' => []],
        ])->renderableBlocks();

        $this->assertSame(['cta'], array_column($blocks, 'type'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Blocks/ReusableBlockTest.php`
Expected: FAIL — `Class "App\Blocks\Types\ReusableBlockType" not found`.

- [ ] **Step 3: Write the block type**

Create `app/Blocks/Types/ReusableBlockType.php`:

```php
<?php

namespace App\Blocks\Types;

use App\Blocks\Block;
use App\Models\ReusableBlock;
use Filament\Forms\Components\Select;

/**
 * A reference to an entry in the reusable block library.
 *
 * Filament persists only `type` and `data`, so a reference cannot be a flag on
 * a normal block — it has to be its own type. HasBlocks::renderableBlocks()
 * swaps it for the library entry's own type and data before rendering.
 *
 * Named ...Type to avoid colliding with App\Models\ReusableBlock.
 */
class ReusableBlockType extends Block
{
    public static function type(): string
    {
        return 'reusable';
    }

    public static function label(): string
    {
        return 'From library';
    }

    public static function icon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function schema(): array
    {
        return [
            Select::make('ref')
                ->label('Library block')
                ->options(fn (): array => ReusableBlock::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->helperText('Editing the library entry updates every page that uses it.'),
        ];
    }

    /**
     * This type is never rendered directly — renderableBlocks() resolves it away.
     * A view is declared only so the registry's "view exists" test has a target.
     */
    public static function view(): string
    {
        return 'blocks.reusable';
    }
}
```

- [ ] **Step 4: Register it**

Append to the singleton closure in `app/Providers/AppServiceProvider.php`:

```php
    ->register(\App\Blocks\Types\ReusableBlockType::class);
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Unit/Blocks/ReusableBlockTest.php tests/Unit/Concerns/HasBlocksTest.php`
Expected: PASS, except the registry's "view exists" test until Task 5 adds `blocks/reusable.blade.php`. Note `test_exactly_nine_content_blocks_are_registered` in `BlockSchemasTest` now fails — update it to expect 10 and rename it to `test_ten_block_types_are_registered`, since `reusable` is a legitimate registered type.

**Prove the live-reference behaviour has teeth:** in `renderableBlocks()`, temporarily copy `$data` instead of taking `$entry->data`, confirm `test_editing_the_library_entry_changes_every_referencing_page` fails, then restore.

- [ ] **Step 6: Commit**

```bash
git add app/Blocks/Types/ReusableBlockType.php app/Providers/AppServiceProvider.php tests/Unit/Blocks
git commit -m "feat: add reusable block type with live library references"
```

---

### Task 5: Block views and the renderer

Transcribe the existing nine partials into block views. **The markup already exists** — it was itself transcribed from the reference design and is verified working in a browser. Do not redesign it; move it and swap its data bindings.

**Files:**
- Create: `resources/views/blocks/{hero,text-image,stats,marquee,horizontal-scroll,stacked-cards,post-list,cta,contact,reusable}.blade.php`
- Create: `resources/views/components/block-renderer.blade.php`
- Test: `tests/Feature/Blocks/BlockRenderingTest.php`

**Interfaces:**
- Consumes: `renderableBlocks()` entries shaped `['type' => string, 'data' => array, 'index' => int]`.
- Produces: a renderer usable as `<x-block-renderer :blocks="$page->renderableBlocks()" />`.

**Source of the markup:** `resources/views/partials/home/*.blade.php` — `hero`, `about` (→ `text-image`), `marquee`, `district` (→ `horizontal-scroll`), `facilities` (→ `stacked-cards`), `news` (→ `post-list`), `contact`. The stats markup lives inside `about.blade.php`; extract it. `cta` has no existing partial — build it from the CTA button markup in `header.blade.php`.

**Every block view must satisfy this envelope:**

```blade
<section data-block="{{ $type }}" data-block-index="{{ $index }}" style="...">
```

and every translatable leaf it renders must carry `data-i18n="{{ $index }}.{{ $field }}"`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Blocks/BlockRenderingTest.php`:

```php
<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockRegistry;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    public static function renderableTypes(): array
    {
        return [
            ['hero'], ['text-image'], ['stats'], ['marquee'],
            ['horizontal-scroll'], ['stacked-cards'], ['post-list'],
            ['cta'], ['contact'],
        ];
    }

    private function render(array $blocks): string
    {
        $page = Page::create([
            'title' => ['en' => 'T'], 'slug' => 'p-'.uniqid(),
            'blocks' => $blocks, 'status' => 'published',
        ]);

        return Blade::render(
            '<x-block-renderer :blocks="$blocks" />',
            ['blocks' => $page->renderableBlocks()],
        );
    }

    #[DataProvider('renderableTypes')]
    public function test_each_block_renders_its_envelope(string $type): void
    {
        $html = $this->render([['type' => $type, 'data' => []]]);

        $this->assertStringContainsString('data-block="'.$type.'"', $html);
        $this->assertStringContainsString('data-block-index="0"', $html);
    }

    public function test_blocks_render_in_stored_order(): void
    {
        $html = $this->render([
            ['type' => 'cta', 'data' => []],
            ['type' => 'hero', 'data' => []],
        ]);

        $this->assertLessThan(
            strpos($html, 'data-block="hero"'),
            strpos($html, 'data-block="cta"'),
            'Blocks did not render in stored order',
        );
    }

    public function test_reordering_blocks_reorders_the_output(): void
    {
        $a = $this->render([['type' => 'hero', 'data' => []], ['type' => 'cta', 'data' => []]]);
        $b = $this->render([['type' => 'cta', 'data' => []], ['type' => 'hero', 'data' => []]]);

        $this->assertNotSame(
            strpos($a, 'data-block="hero"') < strpos($a, 'data-block="cta"'),
            strpos($b, 'data-block="hero"') < strpos($b, 'data-block="cta"'),
        );
    }

    public function test_indexes_are_unique_and_contiguous(): void
    {
        $html = $this->render([
            ['type' => 'hero', 'data' => []],
            ['type' => 'cta', 'data' => []],
            ['type' => 'contact', 'data' => []],
        ]);

        preg_match_all('/data-block-index="(\d+)"/', $html, $m);
        $this->assertSame(['0', '1', '2'], $m[1]);
    }

    public function test_two_instances_of_one_type_get_distinct_indexes(): void
    {
        $html = $this->render([
            ['type' => 'hero', 'data' => []],
            ['type' => 'hero', 'data' => []],
        ]);

        preg_match_all('/data-block-index="(\d+)"/', $html, $m);
        $this->assertSame(['0', '1'], $m[1]);
    }

    public function test_a_missing_image_never_emits_an_empty_src(): void
    {
        $html = $this->render([
            ['type' => 'hero', 'data' => []],
            ['type' => 'stacked-cards', 'data' => ['items' => [['title' => ['en' => 'X']]]]],
        ]);

        $this->assertStringNotContainsString('src=""', $html);
    }

    public function test_every_registered_renderable_type_has_a_view(): void
    {
        foreach (app(BlockRegistry::class)->all() as $type => $class) {
            $this->assertTrue(view()->exists($class::view()), "No view for block [{$type}]");
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Blocks/BlockRenderingTest.php`
Expected: FAIL — the `block-renderer` component does not exist.

- [ ] **Step 3: Write the renderer**

Create `resources/views/components/block-renderer.blade.php`:

```blade
@props(['blocks' => []])

@foreach ($blocks as $block)
    @include(
        app(\App\Blocks\BlockRegistry::class)->get($block['type'])::view(),
        ['data' => $block['data'], 'index' => $block['index'], 'type' => $block['type']]
    )
@endforeach
```

`renderableBlocks()` has already filtered unregistered types, so `get()` cannot return null here.

- [ ] **Step 4: Write the block views**

For each type, copy the corresponding partial listed above, then apply these four substitutions throughout:

1. Wrap the root element in the envelope: `data-block="{{ $type }}" data-block-index="{{ $index }}"`.
2. Replace `$data->content->t('hero_line')` style bindings with `data_get($data, 'heading.'.app()->getLocale())` falling back to English — use the helper below rather than repeating the fallback logic.
3. Replace `data-i18n="heroline"` with `data-i18n="{{ $index }}.heading"`, matching the block's own field names.
4. Replace repeated model loops (`$data->places`, `$data->facilities`, `$data->stats`) with `data_get($data, 'items', [])`.

Add a Blade helper so the locale fallback is written once. Create `app/Support/BlockText.php`:

```php
<?php

namespace App\Support;

use App\Models\SiteSetting;

class BlockText
{
    /**
     * Read a translatable leaf from block data with per-key English fallback,
     * matching HasTranslatableFields::t() semantics for model columns.
     */
    public static function get(array $data, string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $map = data_get($data, $key, []);

        if (! is_array($map)) {
            return (string) $map;
        }

        return (string) (filled($map[$locale] ?? null)
            ? $map[$locale]
            : ($map[SiteSetting::FALLBACK_LOCALE] ?? ''));
    }

    /** Escape, then convert newlines to <br> for the char-split animation. */
    public static function html(array $data, string $key, ?string $locale = null): string
    {
        return str_replace(["\r\n", "\n", "\r"], '<br>', e(static::get($data, $key, $locale)));
    }
}
```

Order matters in `html()`: escaping after the newline conversion would escape the `<br>` tags themselves.

`blocks/reusable.blade.php` renders nothing — `renderableBlocks()` resolves the type away before the renderer sees it. It exists so the registry's view test has a target:

```blade
{{-- Never rendered: HasBlocks::renderableBlocks() resolves a reusable
     reference to the referenced block's own type and data first. --}}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Blocks/BlockRenderingTest.php`
Expected: PASS — 15 tests.

**Prove `test_indexes_are_unique_and_contiguous` has teeth:** change `renderableBlocks()` to emit the stored array key rather than `count($out)`, insert an unregistered block first, and confirm the test fails on the resulting gap. Restore.

- [ ] **Step 6: Commit**

```bash
git add resources/views/blocks resources/views/components/block-renderer.blade.php app/Support/BlockText.php tests/Feature/Blocks
git commit -m "feat: add block views and renderer"
```

---

### Task 6: JS block registry and scoped animation contexts

This is where block order stops mattering. It also fixes a latent defect in the current code: `reveal.js` queries `#district img` and `#facilities img` **by ID**, and those IDs stop existing the moment sections become blocks.

**Files:**
- Create: `resources/js/blocks/index.js`
- Create: `resources/js/blocks/{hero,textImage,stats,marquee,horizontalScroll,stackedCards,postList,cta,contact}.js`
- Modify: `resources/js/scbd/index.js`
- Test: `tests/Unit/Blocks/RegistryParityTest.php`

**Interfaces:**
- Consumes: `gsap`, `gsap/ScrollTrigger`, `lenis` (installed, exact-pinned); the DOM envelope `[data-block]` / `[data-block-index]` from Task 5; the existing modules in `resources/js/scbd/` (`motion`, `smoothScroll`, `textSplit`, `loader`, `header`, `cursor`, `i18n`).
- Produces: `resources/js/blocks/index.js` exporting `BLOCKS`, an object literal keyed by block type whose keys **must exactly match** `BlockRegistry::all()`. Each value is `(root: HTMLElement) => void` and must open `gsap.context(fn, root)`.

**Format constraint — the parity test parses this file.** Keys must be written as two-space-indented quoted literals so the regex `/^\s{2}'([a-z-]+)':/m` matches:

```js
export const BLOCKS = {
  'hero': initHero,
  'text-image': initTextImage,
  ...
};
```

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Blocks/RegistryParityTest.php`:

```php
<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockRegistry;
use Tests\TestCase;

class RegistryParityTest extends TestCase
{
    public function test_php_and_js_block_registries_declare_the_same_types(): void
    {
        // These two registries are mirrors. If they drift, a block renders with
        // no animation and nothing errors — the failure is silent by nature,
        // which is exactly why it needs a test.
        $js = file_get_contents(base_path('resources/js/blocks/index.js'));
        preg_match_all("/^\s{2}'([a-z-]+)':/m", $js, $m);

        $jsTypes = $m[1];
        sort($jsTypes);

        // 'reusable' is resolved away before rendering, so it has no initialiser.
        $phpTypes = array_values(array_diff(array_keys(app(BlockRegistry::class)->all()), ['reusable']));
        sort($phpTypes);

        $this->assertSame($phpTypes, $jsTypes, 'PHP and JS block registries have drifted apart');
    }

    public function test_the_js_registry_parses_to_a_non_empty_set(): void
    {
        $js = file_get_contents(base_path('resources/js/blocks/index.js'));
        preg_match_all("/^\s{2}'([a-z-]+)':/m", $js, $m);

        $this->assertNotEmpty($m[1], 'Parsed no types from the JS registry — has its formatting changed?');
        $this->assertCount(count(array_unique($m[1])), $m[1], 'Duplicate keys in the JS registry');
    }
}
```

Also update `BlockRegistryTest::test_registry_types_match_the_javascript_registry` from Task 2 to exclude `reusable` the same way, or delete it in favour of this file — say which you chose.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Blocks/RegistryParityTest.php`
Expected: FAIL — `file_get_contents(): Failed to open stream` for `resources/js/blocks/index.js`.

- [ ] **Step 3: Write one initialiser per effect-bearing block**

Each takes the block's root element and scopes everything to it. `hero`:

```js
// resources/js/blocks/hero.js
import gsap from 'gsap';
import { splitElement } from '../scbd/textSplit';

export function initHero(root) {
  return gsap.context(() => {
    root.querySelectorAll('[data-split]').forEach(splitElement);

    // Set the offset via gsap.set, never as literal inline CSS: baking
    // transform:translateY(105%) into the style attribute poisons GSAP's
    // yPercent cache and the chars stay permanently invisible.
    gsap.set(root.querySelectorAll('[data-char]'), { y: 0, yPercent: 105 });
    gsap.to(root.querySelectorAll('[data-char]'), {
      yPercent: 0, duration: 0.85, stagger: 0.014, ease: 'expo.out',
    });

    const wrap = root.querySelector('[data-parallax-wrap]');
    const img = root.querySelector('[data-parallax]');

    if (wrap) {
      gsap.fromTo(wrap,
        { clipPath: 'inset(100% 0% 0% 0%)' },
        { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.1, ease: 'expo.out' });
    }

    if (img && wrap) {
      gsap.to(img, {
        yPercent: 14, ease: 'none',
        scrollTrigger: { trigger: wrap, start: 'top bottom', end: 'bottom top', scrub: true },
      });
    }
  }, root);
}
```

`horizontal-scroll` — the hardest, and the one with a guard that prevents freezing the whole page:

```js
// resources/js/blocks/horizontalScroll.js
import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';
import { splitElement } from '../scbd/textSplit';

export function initHorizontalScroll(root) {
  return gsap.context(() => {
    root.querySelectorAll('[data-split]').forEach(splitElement);
    gsap.set(root.querySelectorAll('[data-char]'), { y: 0, yPercent: 0 });

    const track = root.querySelector('[data-horizontal-track]');
    if (!track) return;

    const overflow = () => track.scrollWidth - window.innerWidth;

    // Pinning with a track that cannot overflow pins the viewport with nowhere
    // to scroll — the whole page appears frozen. This is the only failure mode
    // in the animation layer that breaks the entire site rather than one block.
    if (overflow() <= 0) {
      gsap.set(track, { x: 0 });
      return;
    }

    ScrollTrigger.create({
      trigger: root,
      start: 'top top',
      pin: true,
      scrub: 0.8,
      anticipatePin: 1,
      end: () => `+=${overflow()}`,
      onRefresh: () => gsap.set(track, { x: 0 }),
      animation: gsap.to(track, { x: () => -overflow(), ease: 'none' }),
      invalidateOnRefresh: true,
    });
  }, root);
}
```

`marquee` — note the loop tween is captured and **its** `timeScale` is animated:

```js
// resources/js/blocks/marquee.js
import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

export function initMarquee(root) {
  return gsap.context(() => {
    const marquee = root.querySelector('[data-marquee]');
    if (!marquee) return;

    const seconds = Number(root.dataset.duration) || 26;

    // Hold the loop tween: the velocity boost animates THIS tween's timeScale.
    // Targeting the element instead makes GSAP treat timeScale as an unknown
    // CSS property, and `overwrite` then kills the loop itself.
    const loop = gsap.to(marquee, { xPercent: -50, duration: seconds, ease: 'none', repeat: -1 });

    ScrollTrigger.create({
      trigger: marquee,
      start: 'top bottom',
      end: 'bottom top',
      onUpdate: (self) => {
        const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 900, 3);
        gsap.to(loop, { timeScale: boost, duration: 0.3, overwrite: true });
      },
    });
  }, root);
}
```

`stats`:

```js
// resources/js/blocks/stats.js
import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

export function initStats(root) {
  return gsap.context(() => {
    root.querySelectorAll('[data-count]').forEach((el) => {
      const target = parseFloat(el.dataset.to);
      if (Number.isNaN(target)) return;

      const suffix = el.dataset.suffix || '';
      const plain = el.hasAttribute('data-plain');
      const state = { value: 0 };

      const render = () => {
        const rounded = Math.round(state.value);
        el.textContent = (plain ? String(rounded) : rounded.toLocaleString()) + suffix;
      };

      ScrollTrigger.create({
        trigger: el,
        start: 'top 88%',
        once: true,
        onEnter: () => gsap.to(state, { value: target, duration: 1.6, ease: 'power2.out', onUpdate: render }),
      });
    });
  }, root);
}
```

`stacked-cards`:

```js
// resources/js/blocks/stackedCards.js
import gsap from 'gsap';

export function initStackedCards(root) {
  return gsap.context(() => {
    const cards = Array.from(root.querySelectorAll('[data-card]'));

    cards.forEach((card, i) => {
      if (i === cards.length - 1) return;

      gsap.fromTo(card,
        { scale: 1, y: 0 },
        {
          scale: 0.96 - i * 0.012, y: -12, ease: 'none',
          scrollTrigger: { trigger: cards[i + 1], start: 'top bottom', end: 'top 110px', scrub: 0.4 },
        });
    });
  }, root);
}
```

`text-image`, `post-list`, `cta` and `contact` are short. `text-image` runs the shared reveal
on `[data-fade]` and `[data-reveal]` within `root`; `post-list` binds the `x: 14` hover to each
`[data-news]` row within `root`; `cta` binds the magnetic-button behaviour to `[data-magnetic]`
within `root`; `contact` splits `[data-split]`, sets chars to `yPercent: 105`, and reveals them
via a `ScrollTrigger` with `start: 'top 70%', once: true`. Each wraps its work in
`gsap.context(fn, root)` exactly as above.

- [ ] **Step 4: Write the registry**

Create `resources/js/blocks/index.js`. **Keep the two-space quoted-key format** — the parity test parses it:

```js
import { initHero } from './hero';
import { initTextImage } from './textImage';
import { initStats } from './stats';
import { initMarquee } from './marquee';
import { initHorizontalScroll } from './horizontalScroll';
import { initStackedCards } from './stackedCards';
import { initPostList } from './postList';
import { initCta } from './cta';
import { initContact } from './contact';

/**
 * Mirror of App\Blocks\BlockRegistry. A test asserts the key sets match.
 * 'reusable' has no entry: it is resolved away before rendering.
 */
export const BLOCKS = {
  'hero': initHero,
  'text-image': initTextImage,
  'stats': initStats,
  'marquee': initMarquee,
  'horizontal-scroll': initHorizontalScroll,
  'stacked-cards': initStackedCards,
  'post-list': initPostList,
  'cta': initCta,
  'contact': initContact,
};
```

- [ ] **Step 5: Rewrite the bootstrap**

Replace `resources/js/scbd/index.js`'s per-section calls with page-level concerns plus a block dispatch:

```js
import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

import { prefersReducedMotion } from './motion';
import { createSmoothScroll } from './smoothScroll';
import { runLoader } from './loader';
import { initHeader } from './header';
import { initCursor } from './cursor';
import { initLanguageSwitcher } from './i18n';
import { splitElement } from './textSplit';
import { BLOCKS } from '../blocks';

gsap.registerPlugin(ScrollTrigger);

export function initScbd() {
  const reduced = prefersReducedMotion();
  const lenis = createSmoothScroll(ScrollTrigger, reduced);

  // Page-level, not per-block.
  initCursor(gsap);
  initLanguageSwitcher(ScrollTrigger);
  initHeader(gsap, lenis);
  runLoader(gsap, ScrollTrigger, lenis, reduced);

  if (reduced) {
    // Resting state for everything the block initialisers would have animated.
    document.querySelectorAll('[data-split]').forEach(splitElement);
    gsap.set('[data-char]', { y: 0, yPercent: 0 });
    gsap.set('[data-fade]', { opacity: 1, y: 0 });
    gsap.set('[data-reveal], [data-block] img', { clipPath: 'inset(0% 0% 0% 0%)', scale: 1 });
    return;
  }

  document.querySelectorAll('[data-block]').forEach((el) => {
    BLOCKS[el.dataset.block]?.(el);
  });

  ScrollTrigger.refresh();
  window.addEventListener('resize', () => ScrollTrigger.refresh());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initScbd);
} else {
  initScbd();
}
```

The loader now reveals the first block rather than choreographing a handoff to `#top` — the cross-section timeline traded away when blocks were chosen.

- [ ] **Step 6: Build and test**

```bash
npm run build
php artisan test tests/Unit/Blocks/RegistryParityTest.php
```

Expected: build succeeds with no unresolved imports; both parity tests pass.

**Prove the parity test has teeth:** remove one key from `BLOCKS`, confirm the test fails naming the missing type, restore. Report the observed failure message.

- [ ] **Step 7: Commit**

```bash
git add resources/js/blocks resources/js/scbd/index.js tests/Unit/Blocks/RegistryParityTest.php
git commit -m "feat: add per-block scoped animation contexts mirroring the PHP registry"
```

---

### Task 7: `PageData` DTO and the i18n payload

**Files:**
- Create: `app/Support/PageData.php`
- Test: `tests/Feature/PageDataTest.php`

**Interfaces:**
- Consumes: `App\Models\Page::renderableBlocks()`; `App\Models\SiteSetting::singleton()` and `SiteSetting::LOCALES` (`['en' => 'English', 'id' => 'Indonesian', 'cn' => '中文']`); `App\Models\PublicMenuItem` scopes `links()` and `cta()`; `App\Support\BlockText::html()`.
- Produces: `App\Support\PageData` — `final readonly class` with public promoted properties `page`, `settings`, `menu`, `cta`, `blocks`, `i18n`; static `forPage(Page $page): self`. Replaces `App\Support\HomepageData`, which Task 13 deletes.

**The payload contract:** keys are `{index}.{field}`, matching the `data-i18n` attributes the block views emit. A feature test asserts the two sets are identical — neither side may carry an entry the other lacks, because the switcher silently skips keys it cannot find.

**Which fields are translatable** is derived from the data itself, not from a hardcoded list: a leaf is translatable when its value is an array whose keys are a subset of `SiteSetting::LOCALES`. That keeps the payload builder working when a block gains a field, with no second place to update.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PageDataTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PublicMenuItem;
use App\Support\PageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageDataTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks): Page
    {
        return Page::create([
            'title' => ['en' => 'Home'], 'slug' => 'home-'.uniqid(),
            'blocks' => $blocks, 'status' => 'published',
        ]);
    }

    public function test_it_builds_for_a_page_with_no_blocks(): void
    {
        $data = PageData::forPage($this->page([]));

        $this->assertSame([], $data->blocks);
        $this->assertSame(['en', 'id', 'cn'], array_keys($data->i18n));
    }

    public function test_the_payload_covers_all_three_locales(): void
    {
        $data = PageData::forPage($this->page([
            ['type' => 'hero', 'data' => ['heading' => ['en' => 'A', 'id' => 'B', 'cn' => 'C']]],
        ]));

        $this->assertSame('A', $data->i18n['en']['0.heading']);
        $this->assertSame('B', $data->i18n['id']['0.heading']);
        $this->assertSame('C', $data->i18n['cn']['0.heading']);
    }

    public function test_it_falls_back_to_english_per_key(): void
    {
        $data = PageData::forPage($this->page([
            ['type' => 'hero', 'data' => [
                'heading' => ['en' => 'English only'],
                'sub' => ['en' => 'Sub', 'id' => 'Sub ID'],
            ]],
        ]));

        $this->assertSame('English only', $data->i18n['cn']['0.heading']);
        $this->assertSame('Sub ID', $data->i18n['id']['0.sub']);
    }

    public function test_keys_are_scoped_by_block_index(): void
    {
        $data = PageData::forPage($this->page([
            ['type' => 'hero', 'data' => ['heading' => ['en' => 'First']]],
            ['type' => 'hero', 'data' => ['heading' => ['en' => 'Second']]],
        ]));

        $this->assertSame('First', $data->i18n['en']['0.heading']);
        $this->assertSame('Second', $data->i18n['en']['1.heading']);
    }

    public function test_newlines_become_br_and_html_is_escaped(): void
    {
        $data = PageData::forPage($this->page([
            ['type' => 'hero', 'data' => ['heading' => ['en' => "One\nTwo"], 'sub' => ['en' => '<script>x</script>']]],
        ]));

        $this->assertSame('One<br>Two', $data->i18n['en']['0.heading']);
        $this->assertStringNotContainsString('<script>', $data->i18n['en']['0.sub']);
    }

    public function test_non_translatable_leaves_are_absent_from_the_payload(): void
    {
        // image and cta_url are plain scalars, not {en,id,cn} maps.
        $data = PageData::forPage($this->page([
            ['type' => 'hero', 'data' => [
                'heading' => ['en' => 'H'], 'image' => 'uploads/x.jpg', 'cta_url' => '#contact',
            ]],
        ]));

        $this->assertArrayHasKey('0.heading', $data->i18n['en']);
        $this->assertArrayNotHasKey('0.image', $data->i18n['en']);
        $this->assertArrayNotHasKey('0.cta_url', $data->i18n['en']);
    }

    public function test_repeater_items_are_addressed_by_dotted_path(): void
    {
        $data = PageData::forPage($this->page([
            ['type' => 'stats', 'data' => ['items' => [
                ['label' => ['en' => 'Hectares'], 'value' => 45],
                ['label' => ['en' => 'Established'], 'value' => 1987],
            ]]],
        ]));

        $this->assertSame('Hectares', $data->i18n['en']['0.items.0.label']);
        $this->assertSame('Established', $data->i18n['en']['0.items.1.label']);
    }

    public function test_it_separates_nav_links_from_the_cta(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Enquire'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);

        $data = PageData::forPage($this->page([]));

        $this->assertCount(1, $data->menu);
        $this->assertSame('Enquire', $data->cta->t('label', 'en'));
    }

    public function test_nav_labels_are_in_the_payload(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about', 'sort' => 1]);

        $data = PageData::forPage($this->page([]));

        $this->assertSame('Perusahaan', $data->i18n['id']['nav1']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/PageDataTest.php`
Expected: FAIL — `Class "App\Support\PageData" not found`.

- [ ] **Step 3: Write the DTO**

Create `app/Support/PageData.php`:

```php
<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Everything a page needs, assembled once. Blade performs no queries.
 *
 * The i18n payload is keyed {index}.{field} to match the data-i18n attributes
 * block views emit. Index, not a UUID: Filament regenerates block UUIDs on
 * every form load and strips them on save, so anything keyed by them would
 * silently reassign itself.
 */
final readonly class PageData
{
    /**
     * @param  Collection<int, PublicMenuItem>  $menu
     * @param  array<int, array{type: string, data: array, index: int}>  $blocks
     * @param  array<string, array<string, string>>  $i18n
     */
    public function __construct(
        public Page $page,
        public SiteSetting $settings,
        public Collection $menu,
        public ?PublicMenuItem $cta,
        public array $blocks,
        public array $i18n,
    ) {}

    public static function forPage(Page $page): self
    {
        $menu = PublicMenuItem::query()->links()->get();
        $cta = PublicMenuItem::query()->cta()->first();
        $blocks = $page->renderableBlocks();

        return new self(
            page: $page,
            settings: SiteSetting::singleton(),
            menu: $menu,
            cta: $cta,
            blocks: $blocks,
            i18n: self::payload($blocks, $menu, $cta),
        );
    }

    /**
     * @param  array<int, array{type: string, data: array, index: int}>  $blocks
     * @param  Collection<int, PublicMenuItem>  $menu
     * @return array<string, array<string, string>>
     */
    private static function payload(array $blocks, Collection $menu, ?PublicMenuItem $cta): array
    {
        $out = [];

        foreach (array_keys(SiteSetting::LOCALES) as $locale) {
            $bucket = [];

            foreach ($blocks as $block) {
                foreach (self::leaves($block['data'], (string) $block['index']) as $key => $map) {
                    $bucket[$key] = self::html($map, $locale);
                }
            }

            foreach ($menu->values() as $i => $item) {
                $bucket['nav'.($i + 1)] = e((string) $item->t('label', $locale));
            }

            if ($cta !== null) {
                $bucket['cta'] = e((string) $cta->t('label', $locale));
            }

            $out[$locale] = $bucket;
        }

        return $out;
    }

    /**
     * Walk block data and collect every translatable leaf, keyed by dotted path.
     *
     * A leaf is translatable when it is an array whose keys are a subset of the
     * supported locales. Deriving it from the data means a block gaining a field
     * needs no change here.
     *
     * @return array<string, array<string, string|null>>
     */
    private static function leaves(array $data, string $prefix): array
    {
        $locales = array_keys(SiteSetting::LOCALES);
        $found = [];

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $path = $prefix.'.'.$key;

            if ($value !== [] && array_diff(array_keys($value), $locales) === []) {
                $found[$path] = $value;

                continue;
            }

            $found = array_merge($found, self::leaves($value, $path));
        }

        return $found;
    }

    /**
     * @param  array<string, string|null>  $map
     */
    private static function html(array $map, string $locale): string
    {
        $value = filled($map[$locale] ?? null)
            ? $map[$locale]
            : ($map[SiteSetting::FALLBACK_LOCALE] ?? '');

        // Escape first, then convert newlines — the other order would escape the tags.
        return str_replace(["\r\n", "\n", "\r"], '<br>', e((string) $value));
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test tests/Feature/PageDataTest.php`
Expected: PASS — 9 tests.

**Two teeth-checks, both worth doing:**
1. Swap the order in `html()` so it escapes after the newline replacement; confirm `test_newlines_become_br_and_html_is_escaped` fails with `&lt;br&gt;`. Restore.
2. Change `leaves()` to accept any array as translatable; confirm `test_non_translatable_leaves_are_absent_from_the_payload` fails. Restore.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PageData.php tests/Feature/PageDataTest.php
git commit -m "feat: add PageData DTO with index-keyed i18n payload"
```

---

### Task 8: Routing

**Files:**
- Create: `app/Http/Controllers/PageController.php`
- Create: `resources/views/page.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PageRoutingTest.php`

**Interfaces:**
- Consumes: `Page::homepage()`, `Page::published()`, `PageData::forPage()`, `<x-block-renderer>`, `<x-layouts.public>`.
- Produces: `PageController::__invoke(?string $slug = null): View`; named routes `home` and `page`.

**Route ordering is load-bearing.** `/{slug}` is a catch-all. Registered before Story's blog routes or Filament's panel it would shadow them, so it must come **last** in `routes/web.php`, and Filament registers its own routes via its service provider (which run first). Add a `where` constraint excluding known prefixes as a second line of defence.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PageRoutingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'Test'],
            'slug' => 'test',
            'status' => 'published',
            'blocks' => [['type' => 'cta', 'data' => ['heading' => ['en' => 'Hello']]]],
        ], $attrs));
    }

    public function test_the_root_resolves_the_homepage_flagged_row(): void
    {
        $this->make(['slug' => 'other']);
        $this->make(['slug' => 'home', 'is_homepage' => true, 'title' => ['en' => 'Landing']]);

        $this->get('/')->assertSuccessful()->assertSee('data-block="cta"', false);
    }

    public function test_the_root_404s_when_no_homepage_is_flagged(): void
    {
        $this->make(['slug' => 'other']);

        $this->get('/')->assertNotFound();
    }

    public function test_a_published_page_resolves_by_slug(): void
    {
        $this->make(['slug' => 'about']);

        $this->get('/about')->assertSuccessful();
    }

    public function test_a_draft_page_404s(): void
    {
        $this->make(['slug' => 'secret', 'status' => 'draft']);

        $this->get('/secret')->assertNotFound();
    }

    public function test_an_unknown_slug_404s(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_the_catch_all_does_not_shadow_the_blog(): void
    {
        // A page whose slug collides with the blog prefix must not hijack it.
        $this->make(['slug' => 'blogs']);

        $this->get('/blogs')->assertSuccessful();
        $this->assertSame('filament-story.index', request()->route()?->getName() ?? 'filament-story.index');
    }

    public function test_the_catch_all_does_not_shadow_the_admin_panel(): void
    {
        $this->make(['slug' => 'superduper']);

        // Unauthenticated admin access redirects to login rather than rendering a page.
        $this->get('/superduper')->assertRedirect();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/PageRoutingTest.php`
Expected: FAIL — `Class "App\Http\Controllers\PageController" not found`.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/PageController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\PageData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class PageController extends Controller
{
    public function __invoke(?string $slug = null): View
    {
        $page = $slug === null
            ? Page::homepage()
            : Page::query()->published()->where('slug', $slug)->first();

        if (! $page) {
            if ($slug === null) {
                Log::warning('No page is flagged as the homepage.');
            }

            abort(404);
        }

        // Both the headings and t() must agree on the locale. Read it through a
        // composing class: SiteSetting::FALLBACK_LOCALE, never the trait's constant.
        $configured = SiteSetting::singleton()->default_locale;
        $locale = array_key_exists($configured, SiteSetting::LOCALES)
            ? $configured
            : SiteSetting::FALLBACK_LOCALE;

        App::setLocale($locale);

        return view('page', ['data' => PageData::forPage($page)]);
    }
}
```

- [ ] **Step 4: Write the page view**

Create `resources/views/page.blade.php`:

```blade
<x-layouts.public :data="$data">
    @include('partials.home.loader')
    @include('partials.home.header', ['data' => $data])

    <main style="position:relative;">
        <x-block-renderer :blocks="$data->blocks" />
    </main>
</x-layouts.public>
```

`partials/home/loader.blade.php` and `header.blade.php` survive Task 13's removals — they are
page chrome, not blocks. Confirm `layouts/public.blade.php` reads only `$data->settings`, and
adjust it if it still references `$data->content`, which `PageData` does not carry.

- [ ] **Step 5: Register the routes**

Replace `routes/web.php`:

```php
<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', PageController::class)->name('home');

// Catch-all: must be registered LAST so it cannot shadow the blog or the panel.
// The where() constraint is a second line of defence in case ordering changes.
Route::get('/{slug}', PageController::class)
    ->where('slug', '^(?!blogs|superduper|storage|build|livewire)[A-Za-z0-9\-_]+$')
    ->name('page');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/PageRoutingTest.php`
Expected: PASS — 7 tests.

**Prove the shadowing guard has teeth:** remove the `where()` constraint and move the catch-all
above the other routes in the file; confirm `test_the_catch_all_does_not_shadow_the_blog` fails.
Restore both.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PageController.php resources/views/page.blade.php routes/web.php tests/Feature/PageRoutingTest.php
git commit -m "feat: resolve pages by slug with the homepage as a flagged row"
```

---

### Task 9: `PageResource`

**Files:**
- Create: `app/Filament/Resources/Pages/PageResource.php` + `Pages/{ListPages,CreatePage,EditPage}.php`
- Test: `tests/Feature/Filament/PageResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\Page`; `app(BlockRegistry::class)->toBuilderBlocks()`; `App\Filament\Support\LocaleTabs`.
- Produces: `PageResource::getUrl('index'|'create'|'edit')` for Task 14's sidebar.

**Two Filament facts that bite here.** Forms materialise every locale key, so an `assertFormSet`
on `title` must expect the full three-key map. And the Builder persists a plain indexed array of
`{type, data}` — do not attempt to add an id column or preserve UUIDs.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/PageResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_and_create_pages_render(): void
    {
        $this->get(PageResource::getUrl('index'))->assertSuccessful();
        $this->get(PageResource::getUrl('create'))->assertSuccessful();
    }

    public function test_it_creates_a_page_with_blocks(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => ['en' => 'About us', 'id' => null, 'cn' => null],
                'slug' => 'about-us',
                'status' => 'published',
                'blocks' => [
                    ['type' => 'hero', 'data' => ['heading' => ['en' => 'Hello', 'id' => null, 'cn' => null]]],
                    ['type' => 'cta', 'data' => ['heading' => ['en' => 'Act', 'id' => null, 'cn' => null], 'button_label' => ['en' => 'Go', 'id' => null, 'cn' => null], 'url' => '#x']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->sole();

        $this->assertSame(['hero', 'cta'], array_column($page->blocks, 'type'));
        $this->assertSame('Hello', $page->blocks[0]['data']['heading']['en']);
    }

    public function test_blocks_round_trip_through_edit_without_losing_order(): void
    {
        $page = Page::create([
            'title' => ['en' => 'P'], 'slug' => 'p', 'status' => 'published',
            'blocks' => [
                ['type' => 'hero', 'data' => []],
                ['type' => 'marquee', 'data' => []],
                ['type' => 'cta', 'data' => []],
            ],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['hero', 'marquee', 'cta'], array_column($page->fresh()->blocks, 'type'));
    }

    public function test_english_title_is_required(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => ['en' => null, 'id' => 'Ada', 'cn' => null], 'slug' => 'x', 'status' => 'draft'])
            ->call('create')
            ->assertHasFormErrors(['title.en' => 'required']);
    }

    public function test_the_slug_must_be_unique(): void
    {
        Page::create(['title' => ['en' => 'A'], 'slug' => 'taken', 'status' => 'published']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => ['en' => 'B'], 'slug' => 'taken', 'status' => 'draft'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_the_builder_offers_every_registered_block(): void
    {
        $this->get(PageResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('Horizontal Scroll')
            ->assertSee('From library');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/PageResourceTest.php`
Expected: FAIL — `Class "App\Filament\Resources\Pages\PageResource" not found`.

- [ ] **Step 3: Generate the scaffold**

```bash
php artisan make:filament-resource Page --panel=admin
```

Note the exact namespace the generator produces and adapt the imports below to match; the
generator's layout wins over the paths guessed here. Remove any generated `Schemas/` or
`Tables/` subdirectories, matching the convention of the existing resources.

- [ ] **Step 4: Write the resource**

```php
<?php

namespace App\Filament\Resources\Pages;

use App\Blocks\BlockRegistry;
use App\Filament\Support\LocaleTabs;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')->schema([
                LocaleTabs::make(fn (string $locale): array => [
                    TextInput::make("title.$locale")
                        ->label('Title')
                        ->required(LocaleTabs::isFallback($locale))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set) use ($locale): void {
                            if ($locale === 'en' && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                ]),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('The public URL path. Generated from the English title.'),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published'])
                    ->default('draft')
                    ->required(),
                Toggle::make('is_homepage')
                    ->label('This is the homepage')
                    ->helperText('Only one page may carry this. It is served at /.'),
            ])->columns(2),

            Builder::make('blocks')
                ->label('Content')
                ->blocks(app(BlockRegistry::class)->toBuilderBlocks())
                ->collapsible()
                ->cloneable()
                ->blockNumbers(false)
                ->columnSpanFull(),

            Section::make('Search & Social')->schema([
                LocaleTabs::make(fn (string $locale): array => [
                    TextInput::make("meta_title.$locale")->label('Meta title')->maxLength(255),
                    Textarea::make("meta_description.$locale")->label('Meta description')->rows(3)->maxLength(500),
                ]),
                FileUpload::make('og_image')
                    ->label('Social share image')
                    ->image()->disk('public')->directory('uploads/pages')
                    ->visibility('public')->maxSize(5120),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Page $record): ?string => $record->t('title', 'en'))
                    ->searchable(query: fn ($query, string $search) => $query->where('slug', 'like', "%{$search}%")),
                TextColumn::make('slug')->color('gray'),
                TextColumn::make('blocks')
                    ->label('Blocks')
                    ->getStateUsing(fn (Page $record): int => count($record->blocks ?? [])),
                IconColumn::make('is_homepage')->boolean()->label('Home'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
            ])
            ->defaultSort('slug')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
```

`TextColumn::make('title')` needs `getStateUsing` because `title` is a JSON column; without it
the table renders a raw array. The custom `searchable(query:)` closure is needed for the same
reason — the default search would compare against JSON.

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Filament/PageResourceTest.php`
Expected: PASS — 6 tests.

**Teeth-check the round trip**, which is the subtle one: change the Builder's field name from
`blocks` to `content`, confirm `test_blocks_round_trip_through_edit_without_losing_order` fails,
restore. Report both outcomes.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Pages tests/Feature/Filament/PageResourceTest.php
git commit -m "feat: add PageResource with the block builder field"
```

---

### Task 10: `ReusableBlockResource`

**Files:**
- Create: `app/Filament/Resources/ReusableBlocks/ReusableBlockResource.php` + `Pages/{ListReusableBlocks,CreateReusableBlock,EditReusableBlock}.php`
- Test: `tests/Feature/Filament/ReusableBlockResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\ReusableBlock`; `app(BlockRegistry::class)`.
- Produces: `ReusableBlockResource::getUrl(...)` for Task 14.

**The interesting part:** a library entry stores one block's `type` and `data`, so the form must
render *that block's* schema once a type is chosen. A `Select` drives a reactive `Section` whose
children come from `BlockRegistry::get($type)::schema()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/ReusableBlockResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ReusableBlocks\Pages\CreateReusableBlock;
use App\Filament\Resources\ReusableBlocks\ReusableBlockResource;
use App\Models\ReusableBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReusableBlockResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(ReusableBlockResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_library_entry(): void
    {
        Livewire::test(CreateReusableBlock::class)
            ->fillForm([
                'name' => 'Facilities',
                'type' => 'stacked-cards',
                'data' => ['heading' => ['en' => 'Services', 'id' => null, 'cn' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = ReusableBlock::query()->sole();

        $this->assertSame('Facilities', $entry->name);
        $this->assertSame('stacked-cards', $entry->type);
        $this->assertSame('Services', $entry->data['heading']['en']);
    }

    public function test_name_and_type_are_required(): void
    {
        Livewire::test(CreateReusableBlock::class)
            ->fillForm(['name' => null, 'type' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'type' => 'required']);
    }

    public function test_the_type_select_excludes_the_reusable_type(): void
    {
        // A library entry that referenced another library entry would recurse.
        $this->get(ReusableBlockResource::getUrl('create'))
            ->assertSuccessful()
            ->assertDontSee('From library');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/ReusableBlockResourceTest.php`
Expected: FAIL — resource class not found.

- [ ] **Step 3: Generate and write the resource**

```bash
php artisan make:filament-resource ReusableBlock --panel=admin
```

Then:

```php
<?php

namespace App\Filament\Resources\ReusableBlocks;

use App\Blocks\BlockRegistry;
use App\Models\ReusableBlock;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReusableBlockResource extends Resource
{
    protected static ?string $model = ReusableBlock::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    /**
     * Block types offered by the library, excluding 'reusable' itself — a
     * library entry pointing at another library entry would recurse.
     *
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $options = [];

        foreach (app(BlockRegistry::class)->all() as $type => $class) {
            if ($type === 'reusable') {
                continue;
            }

            $options[$type] = $class::label();
        }

        asort($options);

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown in the block picker when adding this to a page.'),
                Select::make('type')
                    ->label('Block type')
                    ->options(static::typeOptions())
                    ->required()
                    ->live()
                    ->helperText('Choosing a type loads that block’s fields below.'),
            ])->columns(2),

            // The chosen block's own schema, nested under `data` so it stores in
            // the same shape an inline block would.
            Section::make('Content')
                ->schema(fn (Get $get): array => filled($get('type')) && app(BlockRegistry::class)->has($get('type'))
                    ? app(BlockRegistry::class)->get($get('type'))::schema()
                    : [])
                ->statePath('data')
                ->visible(fn (Get $get): bool => filled($get('type')))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => app(BlockRegistry::class)->has($state)
                        ? app(BlockRegistry::class)->get($state)::label()
                        : $state),
                TextColumn::make('updated_at')->dateTime()->label('Updated'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReusableBlocks::route('/'),
            'create' => Pages\CreateReusableBlock::route('/create'),
            'edit' => Pages\EditReusableBlock::route('/{record}/edit'),
        ];
    }
}
```

If the reactive `Section` with a closure `schema()` does not re-render on type change, the
fallback is a `Section` per block type, each `->visible(fn (Get $get) => $get('type') === '<type>')`
and `->statePath('data')`. That is more verbose but certain. **Report which you used and why.**

- [ ] **Step 4: Run the tests and commit**

Run: `php artisan test tests/Feature/Filament/ReusableBlockResourceTest.php`
Expected: PASS — 4 tests.

```bash
git add app/Filament/Resources/ReusableBlocks tests/Feature/Filament/ReusableBlockResourceTest.php
git commit -m "feat: add reusable block library resource"
```

---
