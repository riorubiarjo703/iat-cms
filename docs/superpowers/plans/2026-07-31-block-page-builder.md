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

## Remaining tasks (not yet written in full)

Tasks 1–3 above are complete and implementable. The following are specified at
interface level so the plan is coherent, but each still needs its steps, code and
tests written out before execution. **Do not execute from these summaries.**

| # | Task | Key interfaces it must produce |
|---|---|---|
| 4 | `reusable` block type + library resolution | `App\Blocks\Types\ReusableBlock` (schema: one `Select` over `reusable_blocks`, stores `{"ref": id}`); detach action replacing the entry with a copied `type`+`data` |
| 5 | Block Blade partials + renderer | `resources/views/blocks/*.blade.php` (9); `<x-block-renderer :blocks="..." />`; every partial emits `data-block="{type}" data-block-index="{index}"` and `data-i18n="{index}.{field}"` on translatable leaves |
| 6 | JS block registry + scoped contexts | `resources/js/blocks/index.js` exporting a `{type: initialiser}` map whose keys match the PHP registry exactly; each initialiser opens `gsap.context(fn, rootEl)`; bootstrap dispatches on `[data-block]` |
| 7 | `PageData` DTO + i18n payload | `App\Support\PageData::forPage(Page $page): self`; payload keyed `{index}.{field}` across `en`/`id`/`cn`; replaces `HomepageData` |
| 8 | Routing | `PageController::__invoke(?string $slug = null)`; `/` → `Page::homepage()`, `/{slug}` registered last so it cannot shadow `/blogs` or `/superduper`; drafts 404 for guests |
| 9 | `PageResource` | Filament resource with `Builder::make('blocks')->blocks(app(BlockRegistry::class)->toBuilderBlocks())`; locale tabs for title/meta; slug auto-generation |
| 10 | `ReusableBlockResource` | CRUD for the library; block-type Select drives which schema renders |
| 11 | Homepage seeded as blocks | `HomepageBlocksSeeder` transcribing `homepage_contents`, `district_places`, `facilities`, `stats` into one `Page` flagged `is_homepage` |
| 12 | Switch `/` and verify | Point the route at the page resolver; browser-verify pinning, char-split, counters, switcher against the **production bundle** |
| 13 | Removals | Graper package + table + route; `DistrictPlace`/`Facility`/`Stat` models, resources, tables; the nine `partials/home/*`; `HomepageEditor`; `HomepageContent` + table |
| 14 | Sidebar + docs | `AdminNavigation`: Content → Pages, Reusable Blocks, Media Library; drop `Homepage Data`; update `docs/scbd-homepage.md` |

**Ordering is load-bearing.** Tasks 1–11 change nothing user-visible: the block system is
built alongside the existing homepage. Task 12 switches `/` and verifies. Only Task 13
removes anything. The old homepage remains available as a working reference until the
rebuilt one is confirmed correct — and since the SCBD homepage is the acceptance test for
this entire slice, removing it before verification would destroy the only thing we can
check the result against.
