# SCBD Homepage + Comprehensive Admin Sidebar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a curated Filament admin sidebar unifying three installed plugins with six new content models, plus a database-driven public homepage at `/` reproducing the SCBD reference design including its GSAP + ScrollTrigger + Lenis animation layer.

**Architecture:** Four independent layers. (1) Content — Eloquent models plus a `HasTranslatableFields` concern storing `{en,id,cn}` JSON, with no Filament or Blade knowledge. (2) Admin — a single `AdminNavigation` class owning the whole sidebar via Filament's `NavigationBuilder`, plus two singleton pages and six resources. (3) Presentation — `HomeController` builds one readonly DTO consumed by Blade partials that emit `data-*` attributes. (4) Animation — ES modules imported through Vite, driven entirely by those `data-*` attributes with no Laravel knowledge.

**Tech Stack:** Laravel 13.23, Filament 5.7.4, PHP 8.3, PostgreSQL (sqlite `:memory:` for tests), Tailwind 4 + Vite 8, PHPUnit 12.5, GSAP 3.12.5, ScrollTrigger 3.12.5, Lenis.

**Spec:** `docs/superpowers/specs/2026-07-30-scbd-homepage-cms-design.md`

## Global Constraints

- Admin panel path is `/superduper`; panel id is `admin`. Configured in `app/Providers/Filament/AdminPanelProvider.php`.
- Locales are exactly `en`, `id`, `cn`. `en` is the fallback for every translatable field.
- English is `required` on every translatable field. `id` and `cn` are optional and fall back to English per key at render time.
- `$view` on a Filament 5 `Page` is a **non-static** `protected string` property (`vendor/filament/filament/src/Pages/BasePage.php:33`). `$navigationIcon`, `$navigationLabel`, `$navigationGroup`, `$model` remain `static`.
- Filament 5 namespaces: form/layout schemas are `Filament\Schemas\Schema` and `Filament\Schemas\Components\{Tabs,Section,Grid,Group}`; fields are `Filament\Forms\Components\*`; table/page actions are `Filament\Actions\*` (NOT `Filament\Tables\Actions\*`); tables are `Filament\Tables\Table`.
- Resource signatures are `public static function form(Schema $schema): Schema` and `public static function table(Table $table): Table` (`vendor/filament/filament/src/Resources/Resource.php:58,68`).
- Tests are **PHPUnit**, not Pest — no Pest is installed. Test classes extend `Tests\TestCase` and use `Illuminate\Foundation\Testing\RefreshDatabase`. Test methods use the `test_` prefix.
- **PHPUnit is 12.5.33, which ships no `AnnotationParser`** — only `AttributeParser` (verified: `vendor/phpunit/phpunit/src/Metadata/Parser/` contains no `AnnotationParser.php`). Doc-block metadata such as `/** @dataProvider x */`, `@test`, `@depends` and `@group` is **silently ignored**. Always use PHP attributes: `#[DataProvider('method')]`, `#[Test]`, `#[Group('x')]`, importing from `PHPUnit\Framework\Attributes\*`.
- Tests run against sqlite `:memory:` (`phpunit.xml`). Never write raw Postgres-only SQL.
- The reference design uses **bespoke CSS, zero Tailwind** (16,528 chars across three `<style>` blocks; 159 inline `style` attributes; component classes `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-block`, `.btn-icon`, `.grayscale`). Port that CSS verbatim. Tailwind remains for the admin panel and future pages — do not convert the homepage to Tailwind.
- Brand colours from the reference: `#ec3013` (accent red), `#201e1d` (near-black), `#f3f2f2` (page background), `#ae1800` (link hover).
- Typeface is Archivo, self-hosted from 9 extracted WOFF2 files. Do not add a Google Fonts CDN link.
- Extracted reference assets live at `/private/tmp/claude-501/-Users-admin-Sites-iat-cms/b9a940e7-fdbf-4039-929a-69a6dc305653/scratchpad/scbd/`:
  - `page.jsx` — the 239-line animation script to port
  - `shell.html` — the page markup to port
  - `style1.css` (2,454 chars, italic `@font-face`), `style2.css` (13,740 chars, design tokens + component classes + normal `@font-face`), `style3.css` (334 chars, page overrides)
  - `res/*.woff2` — the 9 Archivo font files, named by UUID
- **Trait constants cannot be accessed directly** (PHP 8.4.16 here): `HasTranslatableFields::FALLBACK_LOCALE` raises `Error: Cannot access trait constant ... directly`. Read it through a class that composes the trait — use `SiteSetting::FALLBACK_LOCALE`.
- **Reading a `Tabs` component's children:** use `getDefaultChildComponents()`. `getChildComponents()` exists but throws on a freshly-built, unattached component (it requires a container/Livewire context). Verified against Filament 5.7.4.
- **Filament navigation groups and their items cannot both carry icons.** Setting `->icon()` on a `NavigationGroup` while its `NavigationItem`s also have icons throws at render time: "Navigation group [X] has an icon but one or more of its items also have icons... not both." Put icons on the items only. Verified on 5.7.4.
- **NEVER run destructive database commands against the live Postgres database.** Forbidden: `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`, and any `RefreshDatabase` run that resolves to the `pgsql` connection. The live database holds real user accounts and seeded content. Tests use sqlite `:memory:` via `phpunit.xml` — if a test run ever resolves to `pgsql`, STOP and report it rather than proceeding. To reset local content, re-run `HomepageSeeder`, which is idempotent.
- Every task ends with a commit. The project is **not yet a git repository** — Task 1 initialises it.

---

### Task 1: Repository, tooling and asset baseline

**Files:**
- Create: `public/fonts/*.woff2` (9 files)
- Create: `resources/css/scbd.css`
- Create: `resources/js/scbd/index.js` (placeholder, replaced in Task 17)
- Modify: `package.json`, `package-lock.json`
- Modify: `vite.config.js`
- Leave unchanged: `resources/css/app.css` (Tailwind stays admin-only)

**Interfaces:**
- Consumes: nothing.
- Produces: `gsap` and `lenis` importable from `resources/js/*`; `resources/css/scbd.css` containing the reference design system with font URLs rewritten to `/fonts/<uuid>.woff2`; a git repository with one initial commit.

- [ ] **Step 1: Initialise the repository**

The project has no `.git` directory. Verify, then initialise:

```bash
cd /Users/admin/Sites/iat-cms
git rev-parse --is-inside-work-tree 2>&1 | head -1   # expect: fatal: not a git repository
git init
git add -A
git commit -m "chore: initial commit of existing Laravel + Filament scaffold"
```

- [ ] **Step 2: Install the animation libraries**

Pin the exact versions the reference bundles, so behaviour matches:

```bash
npm install --save-exact gsap@3.12.5 lenis@1.1.18
```

Expected: `package.json` gains a `dependencies` block containing both. `gsap@3.12.5` ships ScrollTrigger inside the same package at `gsap/ScrollTrigger`.

- [ ] **Step 3: Copy the Archivo font files**

Keep the UUID filenames. This is deliberate: the reference CSS references each font by UUID, so preserving the names makes the URL rewrite in Step 4 a pure string substitution with no mapping table to get wrong.

```bash
SCBD=/private/tmp/claude-501/-Users-admin-Sites-iat-cms/b9a940e7-fdbf-4039-929a-69a6dc305653/scratchpad/scbd
mkdir -p public/fonts
cp "$SCBD"/res/*.woff2 public/fonts/
ls public/fonts | wc -l    # expect: 9
```

- [ ] **Step 4: Build `resources/css/scbd.css`**

Concatenate the three reference style blocks in order, then rewrite each bare-UUID font URL to a real path:

```bash
SCBD=/private/tmp/claude-501/-Users-admin-Sites-iat-cms/b9a940e7-fdbf-4039-929a-69a6dc305653/scratchpad/scbd
cat "$SCBD"/style1.css "$SCBD"/style2.css "$SCBD"/style3.css > resources/css/scbd.css
python3 - <<'PY'
import re, pathlib
p = pathlib.Path('resources/css/scbd.css')
css = p.read_text()
css = re.sub(r'url\("([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})"\)',
             r'url("/fonts/\1.woff2")', css)
p.write_text(css)
print('rewritten url() count:', css.count('/fonts/'))
PY
```

Expected: `rewritten url() count: 15` — the CSS declares 15 `@font-face` rules across 9 unique files (the three static-weight files are each referenced by the 400, 600 and 800 declarations).

- [ ] **Step 5: Verify no unrewritten font URLs remain**

```bash
grep -nE 'url\("[0-9a-f]{8}-' resources/css/scbd.css || echo "OK: all font URLs rewritten"
```

Expected: `OK: all font URLs rewritten`

- [ ] **Step 6: Register the stylesheet with Vite**

`resources/css/app.css` currently holds the Tailwind import for the admin panel. Leave it alone — `scbd.css` is a separate entry point so the homepage never ships Tailwind and the admin never ships the SCBD design system.

Modify `vite.config.js` to add the new entry point. Read the existing file first, then extend its `input` array to include `'resources/css/scbd.css'` and `'resources/js/scbd/index.js'` alongside the existing entries.

- [ ] **Step 7: Verify the build**

`resources/js/scbd/index.js` does not exist yet, so create a placeholder so the build succeeds:

```bash
mkdir -p resources/js/scbd
printf 'export function initScbd() {}\n' > resources/js/scbd/index.js
npm run build
```

Expected: build succeeds; `public/build/manifest.json` lists entries for `resources/css/scbd.css` and `resources/js/scbd/index.js`.

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json vite.config.js resources/css/scbd.css resources/js/scbd/index.js public/fonts
git commit -m "chore: add gsap/lenis, self-hosted Archivo fonts, SCBD stylesheet entry point"
```

---

### Task 2: `HasTranslatableFields` concern

Every model in Tasks 3–5 depends on this, so it is built and tested first in isolation.

**Files:**
- Create: `app/Concerns/HasTranslatableFields.php`
- Test: `tests/Unit/Concerns/HasTranslatableFieldsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Concerns\HasTranslatableFields` — trait for Eloquent models.
  - Consuming models declare `protected array $translatable = ['col', ...]`.
  - `t(string $key, ?string $locale = null): ?string` — value for `$locale` (default `app()->getLocale()`), falling back to `en` when the requested locale's value is null or blank. Returns `null` when neither exists.
  - `translations(string $key): array` — the full locale map, or `[]` when the column is null.
  - `translatableFields(): array` — the declared `$translatable` list.
  - `getCasts(): array` — parent casts merged with `array` casts for every translatable column.
  - `public const FALLBACK_LOCALE = 'en'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Concerns/HasTranslatableFieldsTest.php`. The test defines its own throwaway model and table so it exercises the trait without depending on any application model:

```php
<?php

namespace Tests\Unit\Concerns;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TranslatableStub extends Model
{
    use HasTranslatableFields;

    protected $table = 'translatable_stubs';

    protected $guarded = [];

    protected array $translatable = ['title', 'body'];

    protected $casts = ['is_active' => 'boolean'];
}

class HasTranslatableFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('translatable_stubs', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_it_casts_translatable_columns_to_array(): void
    {
        $stub = TranslatableStub::create([
            'title' => ['en' => 'Hello', 'id' => 'Halo', 'cn' => '你好'],
        ]);

        $this->assertSame(['en' => 'Hello', 'id' => 'Halo', 'cn' => '你好'], $stub->fresh()->title);
    }

    public function test_it_preserves_casts_declared_on_the_model(): void
    {
        $stub = new TranslatableStub(['is_active' => '1']);

        $this->assertTrue($stub->is_active);
        $this->assertSame('array', $stub->getCasts()['title']);
    }

    public function test_it_returns_the_value_for_the_requested_locale(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'id' => 'Halo']]);

        $this->assertSame('Halo', $stub->t('title', 'id'));
    }

    public function test_it_defaults_to_the_application_locale(): void
    {
        app()->setLocale('id');
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'id' => 'Halo']]);

        $this->assertSame('Halo', $stub->t('title'));
    }

    public function test_it_falls_back_to_english_when_the_locale_is_missing(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello']]);

        $this->assertSame('Hello', $stub->t('title', 'cn'));
    }

    public function test_it_falls_back_to_english_when_the_locale_value_is_blank(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'cn' => '   ']]);

        $this->assertSame('Hello', $stub->t('title', 'cn'));
    }

    public function test_it_returns_null_when_no_value_exists_at_all(): void
    {
        $stub = new TranslatableStub(['title' => null]);

        $this->assertNull($stub->t('title', 'en'));
    }

    public function test_it_returns_the_full_locale_map(): void
    {
        $stub = new TranslatableStub(['body' => ['en' => 'A', 'id' => 'B']]);

        $this->assertSame(['en' => 'A', 'id' => 'B'], $stub->translations('body'));
    }

    public function test_it_returns_an_empty_map_for_a_null_column(): void
    {
        $stub = new TranslatableStub(['body' => null]);

        $this->assertSame([], $stub->translations('body'));
    }

    public function test_it_exposes_the_declared_translatable_fields(): void
    {
        $this->assertSame(['title', 'body'], (new TranslatableStub)->translatableFields());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Concerns/HasTranslatableFieldsTest.php`
Expected: FAIL — `Trait "App\Concerns\HasTranslatableFields" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Concerns/HasTranslatableFields.php`:

```php
<?php

namespace App\Concerns;

trait HasTranslatableFields
{
    public const FALLBACK_LOCALE = 'en';

    /**
     * Merge `array` casts for every translatable column into whatever the model already declares.
     */
    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->translatableFields(), 'array'),
        );
    }

    /**
     * @return array<int, string>
     */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * @return array<string, string|null>
     */
    public function translations(string $key): array
    {
        $value = $this->getAttribute($key);

        return is_array($value) ? $value : [];
    }

    public function t(string $key, ?string $locale = null): ?string
    {
        $map = $this->translations($key);
        $locale ??= app()->getLocale();

        if (filled($map[$locale] ?? null)) {
            return $map[$locale];
        }

        return filled($map[static::FALLBACK_LOCALE] ?? null)
            ? $map[static::FALLBACK_LOCALE]
            : null;
    }
}
```

`filled()` treats a whitespace-only string as blank, which is what makes the blank-value fallback test pass.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Concerns/HasTranslatableFieldsTest.php`
Expected: PASS — 10 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Concerns/HasTranslatableFields.php tests/Unit/Concerns/HasTranslatableFieldsTest.php
git commit -m "feat: add HasTranslatableFields concern with en fallback"
```

---

### Task 3: `HomepageContent` singleton model

**Files:**
- Create: `database/migrations/2026_07_30_100000_create_homepage_contents_table.php`
- Create: `app/Models/HomepageContent.php`
- Test: `tests/Unit/Models/HomepageContentTest.php`

**Interfaces:**
- Consumes: `App\Concerns\HasTranslatableFields` (Task 2) — `t()`, `translations()`, `translatableFields()`.
- Produces:
  - `App\Models\HomepageContent::singleton(): self` — `firstOrCreate(['id' => 1])`, so a fresh database never yields a missing row.
  - `HomepageContent::TRANSLATABLE` — ordered `array<int,string>` of the 14 translatable column names, reused by the Filament form (Task 11) and the i18n payload (Task 13).
  - Instance is `$guarded = []`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/HomepageContentTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\HomepageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_creates_the_row_on_a_fresh_database(): void
    {
        $this->assertSame(0, HomepageContent::query()->count());

        $content = HomepageContent::singleton();

        $this->assertSame(1, $content->id);
        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_singleton_is_idempotent(): void
    {
        $first = HomepageContent::singleton();
        $second = HomepageContent::singleton();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_it_declares_fourteen_translatable_fields(): void
    {
        $this->assertCount(14, HomepageContent::TRANSLATABLE);
        $this->assertSame(HomepageContent::TRANSLATABLE, (new HomepageContent)->translatableFields());
    }

    public function test_translatable_columns_round_trip_as_arrays(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['hero_line' => ['en' => "The district that\nnever sleeps", 'id' => 'Kawasan']]);

        $this->assertSame('Kawasan', $content->fresh()->t('hero_line', 'id'));
    }

    public function test_it_falls_back_to_english_for_an_untranslated_field(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['contact_heading' => ['en' => 'Take an address']]);

        $this->assertSame('Take an address', $content->fresh()->t('contact_heading', 'cn'));
    }

    public function test_plain_columns_are_not_cast_to_array(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['contact_email' => 'hello@scbd.com']);

        $this->assertSame('hello@scbd.com', $content->fresh()->contact_email);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Models/HomepageContentTest.php`
Expected: FAIL — `Class "App\Models\HomepageContent" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_30_100000_create_homepage_contents_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translatable columns hold {en, id, cn} maps. Column order mirrors the
     * order the sections appear on the homepage.
     */
    private const TRANSLATABLE = [
        'brand_sub',
        'hero_line',
        'hero_sub',
        'about_heading',
        'about_body',
        'about_cta_label',
        'district_heading',
        'district_body',
        'facilities_heading',
        'facilities_body',
        'news_heading',
        'news_cta_label',
        'contact_heading',
        'marquee_text',
    ];

    public function up(): void
    {
        Schema::create('homepage_contents', function (Blueprint $table) {
            $table->id();

            foreach (self::TRANSLATABLE as $column) {
                $table->json($column)->nullable();
            }

            $table->string('hero_image')->nullable();
            $table->string('about_image')->nullable();
            $table->string('about_cta_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_contents');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/HomepageContent.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    use HasTranslatableFields;

    /**
     * Ordered list of translatable columns. Reused by the Filament form and the
     * i18n payload builder so the three never drift apart.
     *
     * @var array<int, string>
     */
    public const TRANSLATABLE = [
        'brand_sub',
        'hero_line',
        'hero_sub',
        'about_heading',
        'about_body',
        'about_cta_label',
        'district_heading',
        'district_body',
        'facilities_heading',
        'facilities_body',
        'news_heading',
        'news_cta_label',
        'contact_heading',
        'marquee_text',
    ];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    /**
     * There is only ever one homepage. Creating on read means a fresh database
     * renders the site instead of throwing.
     */
    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Models/HomepageContentTest.php`
Expected: PASS — 6 tests.

- [ ] **Step 6: Run the migration against the real database**

```bash
php artisan migrate
```

Expected: `2026_07_30_100000_create_homepage_contents_table` reported as `DONE`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/HomepageContent.php tests/Unit/Models/HomepageContentTest.php
git commit -m "feat: add HomepageContent singleton model"
```

---

### Task 4: Ordering concerns, `DistrictPlace` and `Facility`

These two models have an identical shape — a translatable title, a translatable body, an image, a sort position and an active flag — so they are built together with the two shared concerns they both use.

**Files:**
- Create: `app/Concerns/Orderable.php`
- Create: `app/Concerns/Activatable.php`
- Create: `database/migrations/2026_07_30_100100_create_district_places_table.php`
- Create: `database/migrations/2026_07_30_100200_create_facilities_table.php`
- Create: `app/Models/DistrictPlace.php`
- Create: `app/Models/Facility.php`
- Test: `tests/Unit/Models/OrderedContentTest.php`

**Interfaces:**
- Consumes: `App\Concerns\HasTranslatableFields` (Task 2).
- Produces:
  - `App\Concerns\Orderable` — adds `scopeOrdered(Builder $query): Builder` ordering by `sort` ascending then `id` ascending (the `id` tiebreak keeps ordering deterministic when several rows share a `sort` value, which happens before the first manual reorder).
  - `App\Concerns\Activatable` — adds `scopeActive(Builder $query): Builder` filtering `is_active = true`.
  - `App\Models\DistrictPlace` — columns `title` *(json)*, `caption` *(json)*, `image`, `sort`, `is_active`. `TRANSLATABLE = ['title', 'caption']`.
  - `App\Models\Facility` — columns `title` *(json)*, `body` *(json)*, `image`, `sort`, `is_active`. `TRANSLATABLE = ['title', 'body']`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/OrderedContentTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\DistrictPlace;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderedContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordered_scope_sorts_by_sort_ascending(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Third'], 'sort' => 3]);
        DistrictPlace::create(['title' => ['en' => 'First'], 'sort' => 1]);
        DistrictPlace::create(['title' => ['en' => 'Second'], 'sort' => 2]);

        $titles = DistrictPlace::query()->ordered()->get()->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['First', 'Second', 'Third'], $titles->all());
    }

    public function test_ordered_scope_breaks_ties_by_id(): void
    {
        $a = DistrictPlace::create(['title' => ['en' => 'A'], 'sort' => 0]);
        $b = DistrictPlace::create(['title' => ['en' => 'B'], 'sort' => 0]);

        $ids = DistrictPlace::query()->ordered()->pluck('id');

        $this->assertSame([$a->id, $b->id], $ids->all());
    }

    public function test_active_scope_excludes_inactive_rows(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Shown'], 'is_active' => true]);
        DistrictPlace::create(['title' => ['en' => 'Hidden'], 'is_active' => false]);

        $titles = DistrictPlace::query()->active()->get()->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['Shown'], $titles->all());
    }

    public function test_rows_default_to_active(): void
    {
        $this->assertTrue(DistrictPlace::create(['title' => ['en' => 'X']])->fresh()->is_active);
        $this->assertTrue(Facility::create(['title' => ['en' => 'Y']])->fresh()->is_active);
    }

    public function test_district_place_translatable_fields(): void
    {
        $this->assertSame(['title', 'caption'], (new DistrictPlace)->translatableFields());
    }

    public function test_facility_translatable_fields(): void
    {
        $this->assertSame(['title', 'body'], (new Facility)->translatableFields());
    }

    public function test_facility_falls_back_to_english(): void
    {
        $facility = Facility::create([
            'title' => ['en' => 'Fire Service', 'id' => 'Pemadam Kebakaran'],
            'body' => ['en' => 'Run in-house, around the clock.'],
        ]);

        $this->assertSame('Pemadam Kebakaran', $facility->t('title', 'id'));
        $this->assertSame('Run in-house, around the clock.', $facility->t('body', 'id'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Models/OrderedContentTest.php`
Expected: FAIL — `Class "App\Models\DistrictPlace" not found`.

- [ ] **Step 3: Write the two concerns**

Create `app/Concerns/Orderable.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Orderable
{
    /**
     * The `id` tiebreak keeps ordering stable when rows share a `sort` value,
     * which is the case for every row created before the first manual reorder.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
```

Create `app/Concerns/Activatable.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Activatable
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 4: Write the migrations**

Create `database/migrations/2026_07_30_100100_create_district_places_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_places', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->json('caption')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_places');
    }
};
```

Create `database/migrations/2026_07_30_100200_create_facilities_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
```

- [ ] **Step 5: Write the models**

Create `app/Models/DistrictPlace.php`:

```php
<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Model;

class DistrictPlace extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['title', 'caption'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}
```

Create `app/Models/Facility.php`:

```php
<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['title', 'body'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Models/OrderedContentTest.php`
Expected: PASS — 7 tests. If `is_active` assertions fail, confirm `getCasts()` in `HasTranslatableFields` merges rather than replaces `parent::getCasts()`.

- [ ] **Step 7: Migrate and commit**

```bash
php artisan migrate
git add app/Concerns/Orderable.php app/Concerns/Activatable.php app/Models/DistrictPlace.php app/Models/Facility.php database/migrations tests/Unit/Models/OrderedContentTest.php
git commit -m "feat: add DistrictPlace and Facility models with ordering concerns"
```

---

### Task 5: `Stat` and `PublicMenuItem`

> **Spec addition made during planning.** The spec states `public_menu_items` supplies both the reference's `nav1`–`nav4` links and the header CTA button, but defined no column distinguishing the two. This task adds `is_cta` (boolean, default false). Exactly one item is expected to carry it; the presentation layer takes the first CTA item and ignores any others.

**Files:**
- Create: `app/Enums/StatFormat.php`
- Create: `database/migrations/2026_07_30_100300_create_stats_table.php`
- Create: `database/migrations/2026_07_30_100400_create_public_menu_items_table.php`
- Create: `app/Models/Stat.php`
- Create: `app/Models/PublicMenuItem.php`
- Test: `tests/Unit/Models/StatTest.php`
- Test: `tests/Unit/Models/PublicMenuItemTest.php`

**Interfaces:**
- Consumes: `HasTranslatableFields` (Task 2), `Orderable` and `Activatable` (Task 4).
- Produces:
  - `App\Enums\StatFormat` — string-backed enum with cases `Plain = 'plain'` and `Thousands = 'thousands'`, plus `label(): string` for Filament option labels.
  - `App\Models\Stat` — columns `label` *(json)*, `value` (decimal 12,2), `suffix`, `format`, `sort`. `TRANSLATABLE = ['label']`. `isPlain(): bool` returns true when `format === StatFormat::Plain`, which the Blade layer maps to the reference's `data-plain` attribute.
  - `App\Models\PublicMenuItem` — columns `label` *(json)*, `url`, `target`, `sort`, `is_active`, `is_cta`. `TRANSLATABLE = ['label']`. `scopeLinks()` returns active non-CTA items ordered; `scopeCta()` returns active CTA items ordered.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Models/StatTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\StatFormat;
use App\Models\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_format_to_the_enum(): void
    {
        $stat = Stat::create([
            'label' => ['en' => 'Hectares'],
            'value' => 45,
            'format' => StatFormat::Plain,
        ]);

        $this->assertSame(StatFormat::Plain, $stat->fresh()->format);
    }

    public function test_it_defaults_to_thousands_format(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Workers'], 'value' => 1200]);

        $this->assertSame(StatFormat::Thousands, $stat->fresh()->format);
    }

    public function test_is_plain_reflects_the_format(): void
    {
        $plain = Stat::create(['label' => ['en' => 'A'], 'value' => 45, 'format' => StatFormat::Plain]);
        $thousands = Stat::create(['label' => ['en' => 'B'], 'value' => 1200, 'format' => StatFormat::Thousands]);

        $this->assertTrue($plain->isPlain());
        $this->assertFalse($thousands->isPlain());
    }

    public function test_it_stores_a_suffix(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Uptime'], 'value' => 99, 'suffix' => '%']);

        $this->assertSame('%', $stat->fresh()->suffix);
    }

    public function test_ordered_scope_applies(): void
    {
        Stat::create(['label' => ['en' => 'Second'], 'value' => 2, 'sort' => 2]);
        Stat::create(['label' => ['en' => 'First'], 'value' => 1, 'sort' => 1]);

        $labels = Stat::query()->ordered()->get()->map(fn ($s) => $s->t('label', 'en'));

        $this->assertSame(['First', 'Second'], $labels->all());
    }

    public function test_enum_labels_are_human_readable(): void
    {
        $this->assertSame('Plain (45)', StatFormat::Plain->label());
        $this->assertSame('Thousands separated (1,200)', StatFormat::Thousands->label());
    }
}
```

Create `tests/Unit/Models/PublicMenuItemTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\PublicMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_scope_returns_active_non_cta_items_in_order(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'News'], 'url' => '#news', 'sort' => 4]);
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Enquire'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);
        PublicMenuItem::create(['label' => ['en' => 'Hidden'], 'url' => '#x', 'sort' => 2, 'is_active' => false]);

        $labels = PublicMenuItem::query()->links()->get()->map(fn ($i) => $i->t('label', 'en'));

        $this->assertSame(['Company', 'News'], $labels->all());
    }

    public function test_cta_scope_returns_only_active_cta_items(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Enquire'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);
        PublicMenuItem::create(['label' => ['en' => 'Old CTA'], 'url' => '#y', 'sort' => 8, 'is_cta' => true, 'is_active' => false]);

        $labels = PublicMenuItem::query()->cta()->get()->map(fn ($i) => $i->t('label', 'en'));

        $this->assertSame(['Enquire'], $labels->all());
    }

    public function test_items_default_to_active_and_not_cta(): void
    {
        $item = PublicMenuItem::create(['label' => ['en' => 'X'], 'url' => '#x'])->fresh();

        $this->assertTrue($item->is_active);
        $this->assertFalse($item->is_cta);
    }

    public function test_target_defaults_to_self(): void
    {
        $this->assertSame('_self', PublicMenuItem::create(['label' => ['en' => 'X'], 'url' => '#x'])->fresh()->target);
    }

    public function test_label_falls_back_to_english(): void
    {
        $item = PublicMenuItem::create(['label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about']);

        $this->assertSame('Perusahaan', $item->t('label', 'id'));
        $this->assertSame('Company', $item->t('label', 'cn'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Models/StatTest.php tests/Unit/Models/PublicMenuItemTest.php`
Expected: FAIL — `Class "App\Enums\StatFormat" not found`.

- [ ] **Step 3: Write the enum**

Create `app/Enums/StatFormat.php`:

```php
<?php

namespace App\Enums;

enum StatFormat: string
{
    /** Render the raw integer — the reference's `data-plain` behaviour, e.g. 45. */
    case Plain = 'plain';

    /** Render with locale thousands separators, e.g. 1,200. */
    case Thousands = 'thousands';

    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain (45)',
            self::Thousands => 'Thousands separated (1,200)',
        };
    }

    /**
     * Options map for Filament `Select::options()`.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
```

- [ ] **Step 4: Write the migrations**

Create `database/migrations/2026_07_30_100300_create_stats_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats', function (Blueprint $table) {
            $table->id();
            $table->json('label')->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->string('suffix')->nullable();
            $table->string('format')->default('thousands');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats');
    }
};
```

Create `database/migrations/2026_07_30_100400_create_public_menu_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_menu_items', function (Blueprint $table) {
            $table->id();
            $table->json('label')->nullable();
            $table->string('url')->default('#');
            $table->string('target')->default('_self');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            // Distinguishes the header CTA button from the ordinary nav links.
            $table->boolean('is_cta')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'is_cta', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_menu_items');
    }
};
```

- [ ] **Step 5: Write the models**

Create `app/Models/Stat.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use App\Enums\StatFormat;
use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'format' => StatFormat::class,
        'sort' => 'integer',
    ];

    /**
     * Maps to the reference's `data-plain` attribute on the count-up element.
     */
    public function isPlain(): bool
    {
        return $this->format === StatFormat::Plain;
    }
}
```

Create `app/Models/PublicMenuItem.php`:

```php
<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicMenuItem extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'is_cta' => 'boolean',
        'sort' => 'integer',
    ];

    /** Ordinary navigation links — the reference's nav1..nav4. */
    public function scopeLinks(Builder $query): Builder
    {
        return $query->active()->where('is_cta', false)->ordered();
    }

    /** The header call-to-action button — the reference's `cta` key. */
    public function scopeCta(Builder $query): Builder
    {
        return $query->active()->where('is_cta', true)->ordered();
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Models/StatTest.php tests/Unit/Models/PublicMenuItemTest.php`
Expected: PASS — 11 tests total.

- [ ] **Step 7: Migrate and commit**

```bash
php artisan migrate
git add app/Enums/StatFormat.php app/Models/Stat.php app/Models/PublicMenuItem.php database/migrations tests/Unit/Models
git commit -m "feat: add Stat and PublicMenuItem models with is_cta discriminator"
```

---

### Task 6: `SiteSetting` singleton model

**Files:**
- Create: `database/migrations/2026_07_30_100500_create_site_settings_table.php`
- Create: `app/Models/SiteSetting.php`
- Test: `tests/Unit/Models/SiteSettingTest.php`

**Interfaces:**
- Consumes: `HasTranslatableFields` (Task 2).
- Produces:
  - `App\Models\SiteSetting::singleton(): self` — `firstOrCreate(['id' => 1])`.
  - `SiteSetting::TRANSLATABLE = ['meta_title', 'meta_description']`.
  - Columns `site_name`, `logo`, `favicon`, `default_locale` (default `'en'`), `available_locales` *(array, default `['en','id','cn']`)*, `social` *(array)*.
  - `SiteSetting::LOCALES = ['en' => 'English', 'id' => 'Indonesian', 'cn' => '中文']` — the single source of truth for locale codes and labels, consumed by the Filament forms (Tasks 11–12) and the i18n payload builder (Task 13).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/SiteSettingTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_creates_the_row_on_a_fresh_database(): void
    {
        $settings = SiteSetting::singleton();

        $this->assertSame(1, $settings->id);
        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_it_defaults_to_english_with_all_three_locales_available(): void
    {
        $settings = SiteSetting::singleton()->fresh();

        $this->assertSame('en', $settings->default_locale);
        $this->assertSame(['en', 'id', 'cn'], $settings->available_locales);
    }

    public function test_locales_constant_lists_the_three_supported_languages(): void
    {
        $this->assertSame(['en' => 'English', 'id' => 'Indonesian', 'cn' => '中文'], SiteSetting::LOCALES);
    }

    public function test_social_links_round_trip_as_an_array(): void
    {
        $settings = SiteSetting::singleton();
        $settings->update(['social' => ['instagram' => 'https://instagram.com/scbd']]);

        $this->assertSame(['instagram' => 'https://instagram.com/scbd'], $settings->fresh()->social);
    }

    public function test_meta_fields_are_translatable(): void
    {
        $settings = SiteSetting::singleton();
        $settings->update(['meta_title' => ['en' => 'SCBD', 'id' => 'SCBD Jakarta']]);

        $this->assertSame('SCBD Jakarta', $settings->fresh()->t('meta_title', 'id'));
        $this->assertSame('SCBD', $settings->fresh()->t('meta_title', 'cn'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Models/SiteSettingTest.php`
Expected: FAIL — `Class "App\Models\SiteSetting" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_30_100500_create_site_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('default_locale')->default('en');
            $table->json('available_locales')->nullable();
            $table->json('social')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/SiteSetting.php`:

```php
<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasTranslatableFields;

    /**
     * Locale codes and their display labels. Single source of truth — the
     * Filament locale tabs and the i18n payload builder both read this.
     *
     * @var array<string, string>
     */
    public const LOCALES = [
        'en' => 'English',
        'id' => 'Indonesian',
        'cn' => '中文',
    ];

    /** @var array<int, string> */
    public const TRANSLATABLE = ['meta_title', 'meta_description'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'available_locales' => 'array',
        'social' => 'array',
    ];

    protected $attributes = [
        'default_locale' => 'en',
    ];

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['available_locales' => array_keys(self::LOCALES)],
        );
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Models/SiteSettingTest.php`
Expected: PASS — 5 tests.

- [ ] **Step 6: Run the full unit suite to confirm nothing regressed**

Run: `php artisan test tests/Unit`
Expected: PASS — all tests from Tasks 2–6.

- [ ] **Step 7: Migrate and commit**

```bash
php artisan migrate
git add app/Models/SiteSetting.php database/migrations tests/Unit/Models/SiteSettingTest.php
git commit -m "feat: add SiteSetting singleton model"
```

---

### Task 7: Reference image fetcher

The seeder needs the nine reference photographs. Downloading is isolated here so it can be tested with a faked HTTP client and so a network failure degrades to a null image rather than aborting the seed.

**Files:**
- Create: `app/Support/ReferenceImageFetcher.php`
- Test: `tests/Unit/Support/ReferenceImageFetcherTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Support\ReferenceImageFetcher::SOURCES` — `array<string, string>` mapping the nine reference slot names to their scbd.com URLs.
  - `fetch(string $slot, string $directory): ?string` — downloads `SOURCES[$slot]` to the `public` disk under `$directory`, returning the stored relative path, or `null` when the slot is unknown, the request fails, or the response is empty. Never throws.
  - `fetchAll(string $slot => string $directory ...): array<string, ?string>` is **not** provided — callers loop over `fetch()` themselves so each failure is independent.
  - Existing files are not re-downloaded: if a file with the derived name already exists on the disk, the existing path is returned.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/ReferenceImageFetcherTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ReferenceImageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferenceImageFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_declares_all_nine_reference_slots(): void
    {
        $this->assertCount(9, ReferenceImageFetcher::SOURCES);
        $this->assertArrayHasKey('hero1', ReferenceImageFetcher::SOURCES);
        $this->assertArrayHasKey('transport', ReferenceImageFetcher::SOURCES);
    }

    public function test_it_stores_a_downloaded_image_and_returns_its_path(): void
    {
        Http::fake([
            'scbd.com/*' => Http::response('binary-image-bytes', 200),
        ]);

        $path = (new ReferenceImageFetcher)->fetch('hero1', 'uploads/homepage');

        $this->assertSame('uploads/homepage/hero1.jpg', $path);
        Storage::disk('public')->assertExists('uploads/homepage/hero1.jpg');
        $this->assertSame('binary-image-bytes', Storage::disk('public')->get($path));
    }

    public function test_it_preserves_the_png_extension_from_the_source_url(): void
    {
        Http::fake(['scbd.com/*' => Http::response('png-bytes', 200)]);

        $path = (new ReferenceImageFetcher)->fetch('publicrealm', 'uploads/district');

        $this->assertSame('uploads/district/publicrealm.png', $path);
    }

    public function test_it_returns_null_for_an_unknown_slot(): void
    {
        Http::fake();

        $this->assertNull((new ReferenceImageFetcher)->fetch('nope', 'uploads/x'));
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_the_request_fails(): void
    {
        Http::fake(['scbd.com/*' => Http::response('', 404)]);

        $this->assertNull((new ReferenceImageFetcher)->fetch('clinic', 'uploads/facilities'));
        Storage::disk('public')->assertMissing('uploads/facilities/clinic.jpg');
    }

    public function test_it_returns_null_when_the_connection_throws(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->assertNull((new ReferenceImageFetcher)->fetch('clinic', 'uploads/facilities'));
    }

    public function test_it_returns_null_on_an_empty_body(): void
    {
        Http::fake(['scbd.com/*' => Http::response('', 200)]);

        $this->assertNull((new ReferenceImageFetcher)->fetch('security', 'uploads/facilities'));
    }

    public function test_it_does_not_redownload_an_existing_file(): void
    {
        Storage::disk('public')->put('uploads/homepage/hero1.jpg', 'already-here');
        Http::fake();

        $path = (new ReferenceImageFetcher)->fetch('hero1', 'uploads/homepage');

        $this->assertSame('uploads/homepage/hero1.jpg', $path);
        $this->assertSame('already-here', Storage::disk('public')->get($path));
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Support/ReferenceImageFetcherTest.php`
Expected: FAIL — `Class "App\Support\ReferenceImageFetcher" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/ReferenceImageFetcher.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Downloads the nine photographs used by the SCBD reference design.
 *
 * Seeding must never hard-fail because a third-party host is unreachable, so
 * every failure path returns null and the caller leaves that image unset.
 */
class ReferenceImageFetcher
{
    /**
     * Slot name => source URL. Slot names match the `data-src` values in the
     * reference markup.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'hero1' => 'https://scbd.com/assets/images/slideshow/slider_1_700.jpg-1707185523.jpg',
        'towers' => 'https://scbd.com/assets/images/slideshow/slider_2_700.jpg-1707185536.jpg',
        'offices' => 'https://scbd.com/assets/images/slideshow/slider_3_700.jpg-1707185550.jpg',
        'hospitality' => 'https://scbd.com/assets/images/facilities/fasilitas6.jpg-1707157253.jpg',
        'publicrealm' => 'https://scbd.com/assets/images/facilities/fasilitas1.png-1707156296.png',
        'fireservice' => 'https://scbd.com/assets/images/facilities/fasilitas_damkar.jpg-1707156741.jpg',
        'clinic' => 'https://scbd.com/assets/images/facilities/fasilitas_klinik.jpg-1707156741.jpg',
        'security' => 'https://scbd.com/assets/images/facilities/fasilitas3.png-1707156925.png',
        'transport' => 'https://scbd.com/assets/images/facilities/fasilitas4.png-1707156055.png',
    ];

    public function fetch(string $slot, string $directory): ?string
    {
        $source = self::SOURCES[$slot] ?? null;

        if ($source === null) {
            Log::warning('Unknown reference image slot.', ['slot' => $slot]);

            return null;
        }

        $path = rtrim($directory, '/').'/'.$slot.'.'.$this->extensionFor($source);
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(20)->get($source);
        } catch (ConnectionException|Throwable $exception) {
            Log::warning('Reference image download failed.', [
                'slot' => $slot,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed() || $response->body() === '') {
            Log::warning('Reference image download returned no usable body.', [
                'slot' => $slot,
                'status' => $response->status(),
            ]);

            return null;
        }

        $disk->put($path, $response->body());

        return $path;
    }

    /**
     * The source filenames end in a real extension after a cache-busting
     * suffix, e.g. `fasilitas1.png-1707156296.png`, so the trailing extension
     * is authoritative.
     */
    private function extensionFor(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Support/ReferenceImageFetcherTest.php`
Expected: PASS — 8 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/ReferenceImageFetcher.php tests/Unit/Support/ReferenceImageFetcherTest.php
git commit -m "feat: add reference image fetcher with graceful offline handling"
```

---

### Task 8: Homepage seeder

All copy below is transcribed from the reference: English from the `data-i18n` elements in `shell.html`, Indonesian and Chinese from the dictionary at `page.jsx:196-216`. District, facility and stat content comes from the reference markup. District places and facilities have no Indonesian or Chinese in the reference, so they seed English only and fall back at render time.

**Files:**
- Create: `database/seeders/data/homepage.php`
- Create: `database/seeders/HomepageSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Seeders/HomepageSeederTest.php`

**Interfaces:**
- Consumes: all six models (Tasks 3–6), `ReferenceImageFetcher` (Task 7).
- Produces:
  - `Database\Seeders\HomepageSeeder` — idempotent. Updates the two singletons in place; for the four list models it seeds only when the table is empty, so re-running never duplicates rows or clobbers editor changes.
  - `database/seeders/data/homepage.php` — returns `array{content: array, menu: array, places: array, facilities: array, stats: array, settings: array}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/HomepageSeederTest.php`:

```php
<?php

namespace Tests\Feature\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\Stat;
use Database\Seeders\HomepageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake(['scbd.com/*' => Http::response('image-bytes', 200)]);
    }

    public function test_it_seeds_every_content_type(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame(1, HomepageContent::query()->count());
        $this->assertSame(1, SiteSetting::query()->count());
        $this->assertSame(5, PublicMenuItem::query()->count());
        $this->assertSame(3, DistrictPlace::query()->count());
        $this->assertSame(4, Facility::query()->count());
        $this->assertSame(3, Stat::query()->count());
    }

    public function test_it_seeds_all_three_locales_for_homepage_copy(): void
    {
        $this->seed(HomepageSeeder::class);
        $content = HomepageContent::singleton();

        $this->assertSame("A district\nthat never\nclocks out", $content->t('hero_line', 'en'));
        $this->assertSame("Kawasan\nyang tak\npernah tidur", $content->t('hero_line', 'id'));
        $this->assertSame("永不\n停歇的\n商务区", $content->t('hero_line', 'cn'));
    }

    public function test_it_seeds_four_nav_links_and_one_cta(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame(
            ['Company', 'District', 'Facilities', 'News'],
            PublicMenuItem::query()->links()->get()->map(fn ($i) => $i->t('label', 'en'))->all(),
        );
        $this->assertSame('Leasing enquiry', PublicMenuItem::query()->cta()->first()->t('label', 'en'));
        $this->assertSame('Ajukan sewa', PublicMenuItem::query()->cta()->first()->t('label', 'id'));
    }

    public function test_it_seeds_the_established_stat_as_plain_format(): void
    {
        $this->seed(HomepageSeeder::class);

        $established = Stat::query()->get()->firstWhere(fn ($s) => (int) $s->value === 1987);

        $this->assertTrue($established->isPlain());
    }

    public function test_it_attaches_downloaded_images(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame('uploads/homepage/hero1.jpg', HomepageContent::singleton()->hero_image);
        Storage::disk('public')->assertExists('uploads/homepage/hero1.jpg');
        $this->assertNotNull(Facility::query()->ordered()->first()->image);
    }

    public function test_it_leaves_images_null_when_the_host_is_unreachable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->seed(HomepageSeeder::class);

        $this->assertNull(HomepageContent::singleton()->hero_image);
        $this->assertSame(3, DistrictPlace::query()->count(), 'Content must still seed without images.');
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(HomepageSeeder::class);
        $this->seed(HomepageSeeder::class);

        $this->assertSame(3, DistrictPlace::query()->count());
        $this->assertSame(5, PublicMenuItem::query()->count());
        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_it_does_not_overwrite_editor_changes_to_list_content(): void
    {
        $this->seed(HomepageSeeder::class);
        DistrictPlace::query()->ordered()->first()->update(['title' => ['en' => 'Renamed by editor']]);

        $this->seed(HomepageSeeder::class);

        $this->assertSame('Renamed by editor', DistrictPlace::query()->ordered()->first()->t('title', 'en'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Seeders/HomepageSeederTest.php`
Expected: FAIL — `Class "Database\Seeders\HomepageSeeder" not found`.

- [ ] **Step 3: Write the content data file**

Create `database/seeders/data/homepage.php`:

```php
<?php

/**
 * SCBD reference content.
 *
 * English copy transcribed from the `data-i18n` elements of the reference
 * markup; Indonesian and Chinese from the reference dictionary. District and
 * facility copy exists in English only in the reference and falls back at
 * render time.
 *
 * `image` values are `ReferenceImageFetcher` slot names, not paths.
 */
return [
    'content' => [
        'brand_sub' => [
            'en' => 'Danayasa Arthatama',
            'id' => 'Danayasa Arthatama',
            'cn' => 'Danayasa Arthatama',
        ],
        'hero_line' => [
            'en' => "A district\nthat never\nclocks out",
            'id' => "Kawasan\nyang tak\npernah tidur",
            'cn' => "永不\n停歇的\n商务区",
        ],
        'hero_sub' => [
            'en' => 'Forty-five hectares in the middle of Jakarta where offices, hotels, retail and public space run as one address — Sudirman Central Business District.',
            'id' => 'Empat puluh lima hektar di jantung Jakarta tempat perkantoran, hotel, ritel dan ruang publik berjalan sebagai satu alamat — Sudirman Central Business District.',
            'cn' => '雅加达中心四十五公顷的土地，写字楼、酒店、零售与公共空间同属一个地址——苏迪曼中央商务区。',
        ],
        'about_heading' => [
            'en' => 'Built by Danayasa Arthatama. Run like a city.',
            'id' => 'Dibangun Danayasa Arthatama. Dikelola seperti sebuah kota.',
            'cn' => 'Danayasa Arthatama 开发，以城市方式运营。',
        ],
        'about_body' => [
            'en' => 'PT Danayasa Arthatama developed and still operates SCBD as a single, coordinated district — masterplanned infrastructure, its own security and fire service, its own clinic, its own parks. Tenants get a business address; the city gets a piece of urban fabric that works.',
            'id' => 'PT Danayasa Arthatama membangun dan mengelola SCBD sebagai satu kawasan terpadu — infrastruktur terencana, unit keamanan dan pemadam sendiri, klinik sendiri, taman sendiri.',
            'cn' => 'PT Danayasa Arthatama 开发并持续运营 SCBD：统一规划的基础设施、自有的安保与消防、自有诊所与公园。',
        ],
        'about_cta_label' => [
            'en' => 'Read the company profile',
            'id' => 'Baca profil perusahaan',
            'cn' => '阅读公司简介',
        ],
        'district_heading' => [
            'en' => "Everything inside\none walk",
            'id' => "Semua dalam\nsatu langkah",
            'cn' => "一步之内\n皆是所需",
        ],
        'district_body' => [
            'en' => 'Scroll sideways through the places that make up the district — towers, hotels, galleries and the open ground between them.',
            'id' => 'Geser ke samping untuk menyusuri tempat-tempat yang membentuk kawasan ini.',
            'cn' => '横向滚动，浏览构成这一园区的建筑与场所。',
        ],
        'facilities_heading' => [
            'en' => "Services that\nrun underneath",
            'id' => "Layanan yang\nbekerja di balik layar",
            'cn' => "看不见的\n运营支撑",
        ],
        'facilities_body' => [
            'en' => 'A district only feels effortless when the infrastructure is deliberate. These four are operated in-house, around the clock.',
            'id' => 'Kawasan terasa mudah hanya bila infrastrukturnya disengaja. Empat layanan ini dikelola sendiri, sepanjang waktu.',
            'cn' => '园区的从容源自刻意的基础设施。以下四项均由内部团队全天候运营。',
        ],
        'news_heading' => [
            'en' => "Latest from\nthe district",
            'id' => "Kabar terbaru\ndari kawasan",
            'cn' => "园区\n最新动态",
        ],
        'news_cta_label' => [
            'en' => 'All news',
            'id' => 'Semua berita',
            'cn' => '全部新闻',
        ],
        'contact_heading' => [
            'en' => "Take an address\nin the district",
            'id' => "Ambil alamat\ndi kawasan ini",
            'cn' => "在此\n落址",
        ],
        'marquee_text' => [
            'en' => 'Offices — Hotels — Retail — Residences — Public Realm',
            'id' => 'Perkantoran — Hotel — Ritel — Residensial — Ruang Publik',
            'cn' => '写字楼 — 酒店 — 零售 — 住宅 — 公共空间',
        ],
        'about_cta_url' => '/pages/company-profile',
        // The reference publishes no email address. Left null rather than inventing one.
        'contact_email' => null,
        'contact_phone' => '+62 (21) 515-2390',
        'contact_address' => "Jl. Jenderal\nSudirman\nKav 52–53",
        'hero_image_slot' => 'hero1',
        'about_image_slot' => 'towers',
    ],

    'menu' => [
        ['label' => ['en' => 'Company', 'id' => 'Perusahaan', 'cn' => '公司'], 'url' => '#about', 'sort' => 1],
        ['label' => ['en' => 'District', 'id' => 'Kawasan', 'cn' => '园区'], 'url' => '#district', 'sort' => 2],
        ['label' => ['en' => 'Facilities', 'id' => 'Fasilitas', 'cn' => '设施'], 'url' => '#facilities', 'sort' => 3],
        ['label' => ['en' => 'News', 'id' => 'Berita', 'cn' => '新闻'], 'url' => '#news', 'sort' => 4],
        ['label' => ['en' => 'Leasing enquiry', 'id' => 'Ajukan sewa', 'cn' => '租赁咨询'], 'url' => '#contact', 'sort' => 5, 'is_cta' => true],
    ],

    'places' => [
        ['title' => ['en' => 'The towers'], 'caption' => ['en' => 'Grade A office'], 'image_slot' => 'offices', 'sort' => 1],
        ['title' => ['en' => 'Places of interest'], 'caption' => ['en' => 'Hospitality & retail'], 'image_slot' => 'hospitality', 'sort' => 2],
        ['title' => ['en' => 'The public realm'], 'caption' => ['en' => 'Open ground'], 'image_slot' => 'publicrealm', 'sort' => 3],
    ],

    'facilities' => [
        [
            'title' => ['en' => 'Fire & emergency'],
            'body' => ['en' => 'A dedicated district fire station with its own appliances and crew, minutes from every tower lobby.'],
            'image_slot' => 'fireservice',
            'sort' => 1,
        ],
        [
            'title' => ['en' => 'District clinic'],
            'body' => ['en' => 'On-site medical care for the working population of the district, open through business hours and on call after them.'],
            'image_slot' => 'clinic',
            'sort' => 2,
        ],
        [
            'title' => ['en' => 'Security & access'],
            'body' => ['en' => 'One command centre covering perimeter, parking and public space — a single chain of responsibility across all 45 hectares.'],
            'image_slot' => 'security',
            'sort' => 3,
        ],
        [
            'title' => ['en' => 'Transport & parking'],
            'body' => ['en' => "Structured parking, shuttle circulation and direct access to the Sudirman corridor's transit spine."],
            'image_slot' => 'transport',
            'sort' => 4,
        ],
    ],

    'stats' => [
        ['label' => ['en' => 'Hectares masterplanned'], 'value' => 45, 'suffix' => null, 'format' => 'thousands', 'sort' => 1],
        ['label' => ['en' => 'Established'], 'value' => 1987, 'suffix' => null, 'format' => 'plain', 'sort' => 2],
        ['label' => ['en' => 'District security & response'], 'value' => 24, 'suffix' => '/7', 'format' => 'thousands', 'sort' => 3],
    ],

    'settings' => [
        'site_name' => 'SCBD',
        'default_locale' => 'en',
        'available_locales' => ['en', 'id', 'cn'],
        'meta_title' => [
            'en' => 'SCBD — Sudirman Central Business District',
            'id' => 'SCBD — Sudirman Central Business District',
            'cn' => 'SCBD — 苏迪曼中央商务区',
        ],
        'meta_description' => [
            'en' => 'Forty-five hectares in the middle of Jakarta where offices, hotels, retail and public space run as one address.',
            'id' => 'Empat puluh lima hektar di jantung Jakarta tempat perkantoran, hotel, ritel dan ruang publik berjalan sebagai satu alamat.',
            'cn' => '雅加达中心四十五公顷的土地，写字楼、酒店、零售与公共空间同属一个地址。',
        ],
    ],
];
```

- [ ] **Step 4: Write the seeder**

Create `database/seeders/HomepageSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\Stat;
use App\Support\ReferenceImageFetcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function __construct(private readonly ReferenceImageFetcher $images = new ReferenceImageFetcher) {}

    public function run(): void
    {
        $data = require database_path('seeders/data/homepage.php');

        $this->seedContent($data['content']);
        $this->seedSettings($data['settings']);
        $this->seedList(PublicMenuItem::class, $data['menu'], 'uploads/menu');
        $this->seedList(DistrictPlace::class, $data['places'], 'uploads/district');
        $this->seedList(Facility::class, $data['facilities'], 'uploads/facilities');
        $this->seedList(Stat::class, $data['stats'], 'uploads/stats');
    }

    /**
     * Singletons are updated in place so re-seeding refreshes reference copy.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedContent(array $attributes): void
    {
        $heroSlot = $attributes['hero_image_slot'] ?? null;
        $aboutSlot = $attributes['about_image_slot'] ?? null;
        unset($attributes['hero_image_slot'], $attributes['about_image_slot']);

        $attributes['hero_image'] = $heroSlot ? $this->images->fetch($heroSlot, 'uploads/homepage') : null;
        $attributes['about_image'] = $aboutSlot ? $this->images->fetch($aboutSlot, 'uploads/homepage') : null;

        HomepageContent::singleton()->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedSettings(array $attributes): void
    {
        SiteSetting::singleton()->update($attributes);
    }

    /**
     * List content seeds only into an empty table, so editor changes survive a
     * re-seed and rows are never duplicated.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function seedList(string $model, array $rows, string $directory): void
    {
        if ($model::query()->exists()) {
            return;
        }

        foreach ($rows as $row) {
            $slot = $row['image_slot'] ?? null;
            unset($row['image_slot']);

            if ($slot !== null) {
                $row['image'] = $this->images->fetch($slot, $directory);
            }

            $model::query()->create($row);
        }
    }
}
```

- [ ] **Step 5: Register it in `DatabaseSeeder`**

Read `database/seeders/DatabaseSeeder.php`, then add `$this->call(HomepageSeeder::class);` inside its `run()` method, after any existing user seeding.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Seeders/HomepageSeederTest.php`
Expected: PASS — 8 tests.

- [ ] **Step 7: Seed the real database**

```bash
php artisan storage:link
php artisan db:seed --class=Database\\Seeders\\HomepageSeeder
ls -la storage/app/public/uploads/homepage
```

Expected: `storage:link` reports the link created (or that it already exists); the seed completes; `hero1.jpg` and `towers.jpg` are present. If scbd.com is unreachable the seed still succeeds with null images — check `storage/logs/laravel.log` for the warnings.

- [ ] **Step 8: Commit**

```bash
git add database/seeders tests/Feature/Seeders
git commit -m "feat: seed SCBD reference content in en/id/cn with reference images"
```

---

### Task 9: Locale tabs helper and singleton-page concern

Two pieces of shared admin plumbing, built together because both exist only to keep Tasks 10–13 short.

**Files:**
- Create: `app/Filament/Support/LocaleTabs.php`
- Create: `app/Concerns/EditsSingletonRecord.php`
- Test: `tests/Unit/Filament/LocaleTabsTest.php`

**Interfaces:**
- Consumes: `SiteSetting::LOCALES` (Task 6), `HasTranslatableFields::FALLBACK_LOCALE` (Task 2).
- Produces:
  - `App\Filament\Support\LocaleTabs::make(Closure $components, ?string $label = null): Tabs` — builds one `Tab` per entry in `SiteSetting::LOCALES`, calling `$components($locale)` to obtain that tab's field array. Tab labels are the locale display names.
  - `App\Filament\Support\LocaleTabs::isFallback(string $locale): bool` — true for `'en'`. Used as the `required()` argument so English is mandatory and other locales are not.
  - `App\Concerns\EditsSingletonRecord` — for Filament `Page` classes. Requires the implementing class to define `protected function singletonRecord(): Model`. Provides `mount(): void` filling the `form` schema from that record, and `save(): void` writing `$this->form->getState()` back and sending a success notification.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Filament/LocaleTabsTest.php`:

```php
<?php

namespace Tests\Unit\Filament;

use App\Filament\Support\LocaleTabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Tests\TestCase;

class LocaleTabsTest extends TestCase
{
    public function test_it_builds_one_tab_per_supported_locale(): void
    {
        $tabs = LocaleTabs::make(fn (string $locale) => [TextInput::make("title.$locale")]);

        $this->assertInstanceOf(Tabs::class, $tabs);
        $this->assertCount(3, $tabs->getChildSchema()->getComponents());
    }

    public function test_tab_labels_are_the_locale_display_names(): void
    {
        $tabs = LocaleTabs::make(fn (string $locale) => [TextInput::make("title.$locale")]);

        $labels = array_map(
            fn ($tab) => $tab->getLabel(),
            $tabs->getChildSchema()->getComponents(),
        );

        $this->assertSame(['English', 'Indonesian', '中文'], $labels);
    }

    public function test_the_closure_receives_each_locale_code(): void
    {
        $seen = [];
        LocaleTabs::make(function (string $locale) use (&$seen) {
            $seen[] = $locale;

            return [TextInput::make("title.$locale")];
        })->getChildSchema()->getComponents();

        $this->assertSame(['en', 'id', 'cn'], $seen);
    }

    public function test_english_is_the_fallback_locale(): void
    {
        $this->assertTrue(LocaleTabs::isFallback('en'));
        $this->assertFalse(LocaleTabs::isFallback('id'));
        $this->assertFalse(LocaleTabs::isFallback('cn'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Filament/LocaleTabsTest.php`
Expected: FAIL — `Class "App\Filament\Support\LocaleTabs" not found`.

- [ ] **Step 3: Write `LocaleTabs`**

Create `app/Filament/Support/LocaleTabs.php`:

```php
<?php

namespace App\Filament\Support;

use App\Concerns\HasTranslatableFields;
use App\Models\SiteSetting;
use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Locale is the outer axis of every translatable form.
 *
 * Wrapping each individual field in its own three-tab group would produce
 * dozens of nested tab sets; one tab per language instead shows a translator
 * their whole job in one place.
 */
final class LocaleTabs
{
    /**
     * @param  Closure(string): array<int, mixed>  $components
     */
    public static function make(Closure $components, ?string $label = null): Tabs
    {
        $tabs = [];

        foreach (SiteSetting::LOCALES as $locale => $name) {
            $tabs[] = Tab::make($name)->schema($components($locale));
        }

        return Tabs::make($label ?? 'Translations')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * English is required everywhere; other locales fall back to it at render
     * time, so a half-finished translation still yields a coherent page.
     */
    public static function isFallback(string $locale): bool
    {
        return $locale === HasTranslatableFields::FALLBACK_LOCALE;
    }
}
```

- [ ] **Step 4: Write `EditsSingletonRecord`**

Create `app/Concerns/EditsSingletonRecord.php`:

```php
<?php

namespace App\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Mount/save cycle for Filament pages that edit a single always-present row.
 *
 * The implementing page must declare `public ?array $data = []` and a `form()`
 * schema whose `statePath` is `'data'`.
 */
trait EditsSingletonRecord
{
    abstract protected function singletonRecord(): Model;

    public function mount(): void
    {
        $this->form->fill($this->singletonRecord()->attributesToArray());
    }

    public function save(): void
    {
        $this->singletonRecord()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Filament/LocaleTabsTest.php`
Expected: PASS — 4 tests. If `getChildSchema()` errors, the component needs a container; wrap the assertion target by rendering through a page instead — but try the direct call first, as `Tabs` builds its child schema eagerly.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Support/LocaleTabs.php app/Concerns/EditsSingletonRecord.php tests/Unit/Filament/LocaleTabsTest.php
git commit -m "feat: add locale tabs helper and singleton-page concern"
```

---

### Task 10: `HomepageEditor` Filament page

**Files:**
- Create: `app/Filament/Pages/HomepageEditor.php`
- Create: `resources/views/filament/pages/homepage-editor.blade.php`
- Test: `tests/Feature/Filament/HomepageEditorTest.php`

**Interfaces:**
- Consumes: `HomepageContent` (Task 3), `LocaleTabs` and `EditsSingletonRecord` (Task 9).
- Produces:
  - `App\Filament\Pages\HomepageEditor` — slug `homepage`, title `Homepage`. Reached at `/superduper/homepage`.
  - `HomepageEditor::getUrl()` — consumed by `AdminNavigation` (Task 14).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/HomepageEditorTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\HomepageEditor;
use App\Models\HomepageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_renders(): void
    {
        $this->get(HomepageEditor::getUrl())->assertSuccessful();
    }

    public function test_it_mounts_with_the_existing_content(): void
    {
        HomepageContent::singleton()->update(['hero_line' => ['en' => 'Existing headline']]);

        // The form materialises every locale key for each translatable field,
        // because the schema declares a field per locale. assertFormSet compares
        // the whole array, so the untranslated locales must be listed as null.
        Livewire::test(HomepageEditor::class)
            ->assertFormSet(['hero_line' => ['en' => 'Existing headline', 'id' => null, 'cn' => null]]);
    }

    public function test_it_mounts_on_a_fresh_database(): void
    {
        $this->assertSame(0, HomepageContent::query()->count());

        Livewire::test(HomepageEditor::class)->assertSuccessful();

        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_it_saves_all_three_locales(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm([
                'hero_line' => ['en' => 'English line', 'id' => 'Baris Indonesia', 'cn' => '中文标题'],
                'hero_sub' => ['en' => 'Sub'],
                'brand_sub' => ['en' => 'Danayasa'],
                'about_heading' => ['en' => 'About'],
                'about_body' => ['en' => 'Body'],
                'about_cta_label' => ['en' => 'Read more'],
                'district_heading' => ['en' => 'District'],
                'district_body' => ['en' => 'Body'],
                'facilities_heading' => ['en' => 'Facilities'],
                'facilities_body' => ['en' => 'Body'],
                'news_heading' => ['en' => 'News'],
                'news_cta_label' => ['en' => 'All news'],
                'contact_heading' => ['en' => 'Contact'],
                'marquee_text' => ['en' => 'Offices — Hotels'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $content = HomepageContent::singleton()->fresh();

        $this->assertSame('English line', $content->t('hero_line', 'en'));
        $this->assertSame('Baris Indonesia', $content->t('hero_line', 'id'));
        $this->assertSame('中文标题', $content->t('hero_line', 'cn'));
    }

    public function test_english_is_required(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm(['hero_line' => ['en' => null, 'id' => 'Ada']])
            ->call('save')
            ->assertHasFormErrors(['hero_line.en' => 'required']);
    }

    public function test_other_locales_are_optional(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm([
                'hero_line' => ['en' => 'Only English'],
                'hero_sub' => ['en' => 'Sub'],
                'brand_sub' => ['en' => 'Danayasa'],
                'about_heading' => ['en' => 'About'],
                'about_body' => ['en' => 'Body'],
                'about_cta_label' => ['en' => 'Read more'],
                'district_heading' => ['en' => 'District'],
                'district_body' => ['en' => 'Body'],
                'facilities_heading' => ['en' => 'Facilities'],
                'facilities_body' => ['en' => 'Body'],
                'news_heading' => ['en' => 'News'],
                'news_cta_label' => ['en' => 'All news'],
                'contact_heading' => ['en' => 'Contact'],
                'marquee_text' => ['en' => 'Offices'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Only English', HomepageContent::singleton()->fresh()->t('hero_line', 'cn'));
    }

    public function test_it_saves_contact_details(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm(['contact_email' => 'test@example.com', 'contact_phone' => '+62 (21) 000-0000'])
            ->call('save');

        $this->assertSame('test@example.com', HomepageContent::singleton()->fresh()->contact_email);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/HomepageEditorTest.php`
Expected: FAIL — `Class "App\Filament\Pages\HomepageEditor" not found`.

**Panel access is required for this page to be reachable.** Filament 5 refuses panel access outside the `local` environment unless the authenticated user's model implements `Filament\Models\Contracts\FilamentUser`. `App\Models\User` does not, so every panel-page test 403s under `APP_ENV=testing`. This task must therefore also add that interface to `App\Models\User`:

```php
class User extends Authenticatable implements FilamentUser
{
    /**
     * Every registered user may reach the admin panel. Accounts are created by
     * administrators through the Users resource, not by public sign-up, so
     * authentication is the access boundary. Tighten this if self-registration
     * is ever added.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
```

This is a security-relevant default and must be covered by a test asserting that an authenticated user can reach the panel, so the posture is explicit rather than incidental.

- [ ] **Step 3: Generate the page scaffold**

```bash
php artisan make:filament-page HomepageEditor --panel=admin
```

This creates `app/Filament/Pages/HomepageEditor.php` and `resources/views/filament/pages/homepage-editor.blade.php`. Overwrite both with the contents from Steps 4 and 5.

- [ ] **Step 4: Write the page**

Replace `app/Filament/Pages/HomepageEditor.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Concerns\EditsSingletonRecord;
use App\Filament\Support\LocaleTabs;
use App\Models\HomepageContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class HomepageEditor extends Page
{
    use EditsSingletonRecord;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $title = 'Homepage';

    protected static ?string $slug = 'homepage';

    /** Non-static in Filament 5. */
    protected string $view = 'filament.pages.homepage-editor';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected function singletonRecord(): Model
    {
        return HomepageContent::singleton();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Homepage')
                    ->tabs([
                        ...$this->localeTabs(),
                        Tab::make('Media & Links')->schema($this->mediaFields()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * One tab per language, each holding every translatable field for that
     * language grouped by page section.
     *
     * @return array<int, Tab>
     */
    private function localeTabs(): array
    {
        return LocaleTabs::make(fn (string $locale) => [
            Section::make('Brand & Navigation')->schema([
                TextInput::make("brand_sub.$locale")
                    ->label('Brand subtitle')
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Hero')->schema([
                Textarea::make("hero_line.$locale")
                    ->label('Hero headline')
                    ->rows(3)
                    ->helperText('Each new line becomes one animated line of the headline.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("hero_sub.$locale")
                    ->label('Hero paragraph')
                    ->rows(3)
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('About')->schema([
                Textarea::make("about_heading.$locale")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("about_body.$locale")->label('Body')->rows(4)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("about_cta_label.$locale")->label('Button label')->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('District')->schema([
                Textarea::make("district_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("district_body.$locale")->label('Body')->rows(3)->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Facilities')->schema([
                Textarea::make("facilities_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("facilities_body.$locale")->label('Body')->rows(3)->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('News')->schema([
                Textarea::make("news_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                TextInput::make("news_cta_label.$locale")->label('Button label')->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Contact')->schema([
                Textarea::make("contact_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Marquee')->schema([
                TextInput::make("marquee_text.$locale")
                    ->label('Scrolling strip text')
                    ->helperText('Repeated automatically to fill the strip.')
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
        ])->getDefaultChildComponents();
    }

    /**
     * @return array<int, mixed>
     */
    private function mediaFields(): array
    {
        return [
            Section::make('Images')->schema([
                FileUpload::make('hero_image')
                    ->label('Hero image')
                    ->image()
                    ->disk('public')
                    ->directory('uploads/homepage')
                    ->visibility('public')
                    ->maxSize(5120),
                FileUpload::make('about_image')
                    ->label('About image')
                    ->image()
                    ->disk('public')
                    ->directory('uploads/homepage')
                    ->visibility('public')
                    ->maxSize(5120),
            ]),
            Section::make('Links & Contact')->schema([
                TextInput::make('about_cta_url')->label('About button URL')->maxLength(255),
                TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                TextInput::make('contact_phone')->label('Phone')->maxLength(255),
                Textarea::make('contact_address')
                    ->label('Address')
                    ->rows(3)
                    ->helperText('Each new line becomes one line in the district location panel.'),
            ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save'),
            Action::make('view')->label('View homepage')->url('/')->openUrlInNewTab()->color('gray'),
        ];
    }
}
```

`getDefaultChildComponents()` — **not** `getChildComponents()` — is used to flatten the locale tabs into the wider tab set that also carries the locale-independent "Media & Links" tab. This was established empirically against Filament 5.7.4 in Task 9: both methods exist, but `getChildComponents()` **throws** on a freshly-built, unattached `Tabs` because it needs a container/Livewire context, whereas `getDefaultChildComponents()` reads the stored array directly and returns the three `Tab` objects. Verified: `getChildComponents()` -> `Error`; `getDefaultChildComponents()` -> 3 tabs.

- [ ] **Step 5: Write the view**

Replace `resources/views/filament/pages/homepage-editor.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Filament/HomepageEditorTest.php`
Expected: PASS — 7 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/HomepageEditor.php resources/views/filament/pages/homepage-editor.blade.php tests/Feature/Filament/HomepageEditorTest.php
git commit -m "feat: add HomepageEditor page with per-locale tabs"
```

---

### Task 11: `SiteSettingsPage`

**Files:**
- Create: `app/Filament/Pages/SiteSettingsPage.php`
- Create: `resources/views/filament/pages/site-settings-page.blade.php`
- Test: `tests/Feature/Filament/SiteSettingsPageTest.php`

**Interfaces:**
- Consumes: `SiteSetting` (Task 6), `LocaleTabs` and `EditsSingletonRecord` (Task 9).
- Produces: `App\Filament\Pages\SiteSettingsPage` — slug `site-settings`, title `Site Settings`. `getUrl()` consumed by `AdminNavigation` (Task 14).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/SiteSettingsPageTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SiteSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_renders(): void
    {
        $this->get(SiteSettingsPage::getUrl())->assertSuccessful();
    }

    public function test_it_mounts_on_a_fresh_database(): void
    {
        Livewire::test(SiteSettingsPage::class)->assertSuccessful();

        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_it_saves_site_name_and_translated_meta(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'site_name' => 'SCBD',
                'default_locale' => 'en',
                'meta_title' => ['en' => 'SCBD', 'id' => 'SCBD Jakarta'],
                'meta_description' => ['en' => 'Forty-five hectares.'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = SiteSetting::singleton()->fresh();

        $this->assertSame('SCBD', $settings->site_name);
        $this->assertSame('SCBD Jakarta', $settings->t('meta_title', 'id'));
        $this->assertSame('SCBD', $settings->t('meta_title', 'cn'));
    }

    public function test_english_meta_title_is_required(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm(['site_name' => 'SCBD', 'meta_title' => ['en' => null]])
            ->call('save')
            ->assertHasFormErrors(['meta_title.en' => 'required']);
    }

    public function test_it_saves_social_links(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'site_name' => 'SCBD',
                'meta_title' => ['en' => 'SCBD'],
                'meta_description' => ['en' => 'Desc'],
                'social' => ['instagram' => 'https://instagram.com/scbd', 'linkedin' => null],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://instagram.com/scbd', SiteSetting::singleton()->fresh()->social['instagram']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/SiteSettingsPageTest.php`
Expected: FAIL — `Class "App\Filament\Pages\SiteSettingsPage" not found`.

- [ ] **Step 3: Generate the scaffold**

```bash
php artisan make:filament-page SiteSettingsPage --panel=admin
```

- [ ] **Step 4: Write the page**

Replace `app/Filament/Pages/SiteSettingsPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Concerns\EditsSingletonRecord;
use App\Filament\Support\LocaleTabs;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SiteSettingsPage extends Page
{
    use EditsSingletonRecord;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Site Settings';

    protected static ?string $slug = 'site-settings';

    protected string $view = 'filament.pages.site-settings-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected function singletonRecord(): Model
    {
        return SiteSetting::singleton();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('site_name')->label('Site name')->required()->maxLength(255),
                        Select::make('default_locale')
                            ->label('Default language')
                            ->options(SiteSetting::LOCALES)
                            ->default('en')
                            ->required(),
                        FileUpload::make('logo')
                            ->image()->disk('public')->directory('uploads/branding')
                            ->visibility('public')->maxSize(2048),
                        FileUpload::make('favicon')
                            ->image()->disk('public')->directory('uploads/branding')
                            ->visibility('public')->maxSize(512),
                    ])
                    ->columns(2),

                LocaleTabs::make(fn (string $locale) => [
                    TextInput::make("meta_title.$locale")
                        ->label('Meta title')
                        ->required(LocaleTabs::isFallback($locale))
                        ->maxLength(255),
                    Textarea::make("meta_description.$locale")
                        ->label('Meta description')
                        ->rows(3)
                        ->required(LocaleTabs::isFallback($locale))
                        ->maxLength(500),
                ], 'Search & Social Preview'),

                Section::make('Social Links')
                    ->schema([
                        TextInput::make('social.instagram')->label('Instagram')->url()->maxLength(255),
                        TextInput::make('social.linkedin')->label('LinkedIn')->url()->maxLength(255),
                        TextInput::make('social.youtube')->label('YouTube')->url()->maxLength(255),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save'),
        ];
    }
}
```

- [ ] **Step 5: Write the view**

Replace `resources/views/filament/pages/site-settings-page.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 6: Run the test and commit**

Run: `php artisan test tests/Feature/Filament/SiteSettingsPageTest.php`
Expected: PASS — 5 tests.

```bash
git add app/Filament/Pages/SiteSettingsPage.php resources/views/filament/pages/site-settings-page.blade.php tests/Feature/Filament/SiteSettingsPageTest.php
git commit -m "feat: add SiteSettingsPage"
```

---

### Task 12: Four reorderable resources

`DistrictPlaceResource`, `FacilityResource`, `StatResource` and `PublicMenuItemResource`. All four follow the same shape: a `LocaleTabs` block for translatable fields, locale-independent fields beneath it, and a table with `reorderable('sort')`.

**Files:**
- Create: `app/Filament/Resources/DistrictPlaces/DistrictPlaceResource.php` + `Pages/{ListDistrictPlaces,CreateDistrictPlace,EditDistrictPlace}.php`
- Create: `app/Filament/Resources/Facilities/FacilityResource.php` + `Pages/{ListFacilities,CreateFacility,EditFacility}.php`
- Create: `app/Filament/Resources/Stats/StatResource.php` + `Pages/{ListStats,CreateStat,EditStat}.php`
- Create: `app/Filament/Resources/PublicMenuItems/PublicMenuItemResource.php` + `Pages/{ListPublicMenuItems,CreatePublicMenuItem,EditPublicMenuItem}.php`
- Test: `tests/Feature/Filament/OrderedResourcesTest.php`

**Interfaces:**
- Consumes: the four models (Tasks 4–5), `StatFormat` (Task 5), `LocaleTabs` (Task 9).
- Produces: four resource classes, each exposing `getUrl()` for `AdminNavigation` (Task 14). None of them set `$navigationGroup` or `$navigationLabel` — Task 14's navigation builder supplies all placement, so setting them here would be dead code.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/OrderedResourcesTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enums\StatFormat;
use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use App\Filament\Resources\DistrictPlaces\Pages\CreateDistrictPlace;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\PublicMenuItems\Pages\CreatePublicMenuItem;
use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use App\Filament\Resources\Stats\Pages\CreateStat;
use App\Filament\Resources\Stats\StatResource;
use App\Models\DistrictPlace;
use App\Models\PublicMenuItem;
use App\Models\Stat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderedResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public static function resourceProvider(): array
    {
        return [
            'district places' => [DistrictPlaceResource::class],
            'facilities' => [FacilityResource::class],
            'stats' => [StatResource::class],
            'public menu items' => [PublicMenuItemResource::class],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_the_index_page_renders(string $resource): void
    {
        $this->get($resource::getUrl('index'))->assertSuccessful();
    }

    #[DataProvider('resourceProvider')]
    public function test_the_create_page_renders(string $resource): void
    {
        $this->get($resource::getUrl('create'))->assertSuccessful();
    }

    public function test_it_creates_a_district_place_with_translations(): void
    {
        Livewire::test(CreateDistrictPlace::class)
            ->fillForm([
                'title' => ['en' => 'The towers', 'id' => 'Menara'],
                'caption' => ['en' => 'Grade A office'],
                'sort' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $place = DistrictPlace::query()->sole();

        $this->assertSame('The towers', $place->t('title', 'en'));
        $this->assertSame('Menara', $place->t('title', 'id'));
        $this->assertSame('The towers', $place->t('title', 'cn'));
    }

    public function test_english_title_is_required(): void
    {
        Livewire::test(CreateDistrictPlace::class)
            ->fillForm(['title' => ['en' => null, 'id' => 'Menara']])
            ->call('create')
            ->assertHasFormErrors(['title.en' => 'required']);
    }

    public function test_it_creates_a_stat_with_a_format_and_suffix(): void
    {
        Livewire::test(CreateStat::class)
            ->fillForm([
                'label' => ['en' => 'District security & response'],
                'value' => 24,
                'suffix' => '/7',
                'format' => StatFormat::Thousands->value,
                'sort' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stat = Stat::query()->sole();

        $this->assertSame(StatFormat::Thousands, $stat->format);
        $this->assertSame('/7', $stat->suffix);
    }

    public function test_it_creates_a_cta_menu_item(): void
    {
        Livewire::test(CreatePublicMenuItem::class)
            ->fillForm([
                'label' => ['en' => 'Leasing enquiry'],
                'url' => '#contact',
                'target' => '_self',
                'sort' => 5,
                'is_active' => true,
                'is_cta' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(PublicMenuItem::query()->sole()->is_cta);
    }

    public function test_the_district_table_is_reorderable_by_sort(): void
    {
        $this->assertSame('sort', DistrictPlaceResource::table(
            app(\Filament\Tables\Table::class, ['livewire' => Livewire::test(
                \App\Filament\Resources\DistrictPlaces\Pages\ListDistrictPlaces::class
            )->instance()])
        )->getReorderColumn());
    }
}
```

If the final reorder assertion proves awkward to construct, replace it with the simpler behavioural check that the list page renders a reorder trigger:

```php
    public function test_the_district_list_page_exposes_reordering(): void
    {
        DistrictPlace::create(['title' => ['en' => 'A']]);

        Livewire::test(\App\Filament\Resources\DistrictPlaces\Pages\ListDistrictPlaces::class)
            ->assertSuccessful()
            ->assertSee('Reorder');
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/OrderedResourcesTest.php`
Expected: FAIL — `Class "App\Filament\Resources\DistrictPlaces\DistrictPlaceResource" not found`.

- [ ] **Step 3: Generate the four scaffolds**

```bash
php artisan make:filament-resource DistrictPlace --panel=admin
php artisan make:filament-resource Facility --panel=admin
php artisan make:filament-resource Stat --panel=admin
php artisan make:filament-resource PublicMenuItem --panel=admin
```

Accept the default answers. Note the generated directory names and adjust the namespaces in Steps 4–7 to match exactly what the generator produced, then overwrite the resource class bodies.

- [ ] **Step 4: Write `DistrictPlaceResource`**

```php
<?php

namespace App\Filament\Resources\DistrictPlaces;

use App\Filament\Support\LocaleTabs;
use App\Models\DistrictPlace;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistrictPlaceResource extends Resource
{
    protected static ?string $model = DistrictPlace::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("title.$locale")
                    ->label('Title')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
                Textarea::make("caption.$locale")
                    ->label('Caption')
                    ->rows(2)
                    ->maxLength(255),
            ]),
            Section::make('Image & Position')->schema([
                FileUpload::make('image')
                    ->image()->disk('public')->directory('uploads/district')
                    ->visibility('public')->maxSize(5120),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible on the homepage')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->disk('public')->imageHeight(56)->label('Image'),
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (DistrictPlace $record) => $record->t('title', 'en')),
                TextColumn::make('caption')
                    ->label('Caption')
                    ->getStateUsing(fn (DistrictPlace $record) => $record->t('caption', 'en')),
                IconColumn::make('is_active')->boolean()->label('Visible'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistrictPlaces::route('/'),
            'create' => Pages\CreateDistrictPlace::route('/create'),
            'edit' => Pages\EditDistrictPlace::route('/{record}/edit'),
        ];
    }
}
```

Because `title` is a JSON column, a plain `TextColumn::make('title')` would render the raw array. `getStateUsing()` with `t('title', 'en')` is what makes the table readable — apply the same treatment to every translatable column in the three resources below.

- [ ] **Step 5: Write `FacilityResource`**

```php
<?php

namespace App\Filament\Resources\Facilities;

use App\Filament\Support\LocaleTabs;
use App\Models\Facility;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("title.$locale")
                    ->label('Title')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
                Textarea::make("body.$locale")
                    ->label('Body')
                    ->rows(4)
                    ->maxLength(1000),
            ]),
            Section::make('Image & Position')->schema([
                FileUpload::make('image')
                    ->image()->disk('public')->directory('uploads/facilities')
                    ->visibility('public')->maxSize(5120),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible on the homepage')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->disk('public')->imageHeight(56)->label('Image'),
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Facility $record) => $record->t('title', 'en')),
                TextColumn::make('body')
                    ->label('Body')
                    ->limit(60)
                    ->getStateUsing(fn (Facility $record) => $record->t('body', 'en')),
                IconColumn::make('is_active')->boolean()->label('Visible'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 6: Write `StatResource`**

```php
<?php

namespace App\Filament\Resources\Stats;

use App\Enums\StatFormat;
use App\Filament\Support\LocaleTabs;
use App\Models\Stat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatResource extends Resource
{
    protected static ?string $model = Stat::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("label.$locale")
                    ->label('Label')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
            ]),
            Section::make('Value')->schema([
                TextInput::make('value')
                    ->label('Counts up to')
                    ->numeric()
                    ->required()
                    ->default(0),
                TextInput::make('suffix')
                    ->label('Suffix')
                    ->helperText('Appended after the number, e.g. /7 or %.')
                    ->maxLength(16),
                Select::make('format')
                    ->label('Number format')
                    ->options(StatFormat::options())
                    ->default(StatFormat::Thousands->value)
                    ->required()
                    ->helperText('Use Plain for years so 1987 is not rendered as 1,987.'),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn (Stat $record) => $record->t('label', 'en')),
                TextColumn::make('value')->label('Value')->numeric(),
                TextColumn::make('suffix')->label('Suffix'),
                TextColumn::make('format')
                    ->label('Format')
                    ->getStateUsing(fn (Stat $record) => $record->format->label()),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
            'create' => Pages\CreateStat::route('/create'),
            'edit' => Pages\EditStat::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 7: Write `PublicMenuItemResource`**

```php
<?php

namespace App\Filament\Resources\PublicMenuItems;

use App\Filament\Support\LocaleTabs;
use App\Models\PublicMenuItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicMenuItemResource extends Resource
{
    protected static ?string $model = PublicMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("label.$locale")
                    ->label('Label')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
            ]),
            Section::make('Destination & Position')->schema([
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->default('#')
                    ->helperText('An anchor such as #about scrolls smoothly; a path such as /blogs navigates.')
                    ->maxLength(255),
                Select::make('target')
                    ->label('Opens in')
                    ->options(['_self' => 'Same tab', '_blank' => 'New tab'])
                    ->default('_self')
                    ->required(),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible')->default(true),
                Toggle::make('is_cta')
                    ->label('Render as the header call-to-action button')
                    ->helperText('Only one item should carry this. The first one wins.')
                    ->default(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn (PublicMenuItem $record) => $record->t('label', 'en')),
                TextColumn::make('url')->label('URL'),
                IconColumn::make('is_cta')->boolean()->label('CTA'),
                IconColumn::make('is_active')->boolean()->label('Visible'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicMenuItems::route('/'),
            'create' => Pages\CreatePublicMenuItem::route('/create'),
            'edit' => Pages\EditPublicMenuItem::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 8: Run the tests and commit**

Run: `php artisan test tests/Feature/Filament/OrderedResourcesTest.php`
Expected: PASS. If `recordActions()` or `toolbarActions()` are unrecognised, check the generated scaffold from Step 3 for the exact method names this Filament build uses and match them.

```bash
git add app/Filament/Resources tests/Feature/Filament/OrderedResourcesTest.php
git commit -m "feat: add four reorderable homepage content resources"
```

---

### Task 13: `BlogCategoryResource` and `UserResource`

Story ships a `BlogCategory` model with no admin UI, so categories are currently unmanageable. Nothing user-facing is registered either. Both gaps are closed here.

**Files:**
- Create: `app/Filament/Resources/BlogCategories/BlogCategoryResource.php` + `Pages/{ListBlogCategories,CreateBlogCategory,EditBlogCategory}.php`
- Create: `app/Filament/Resources/Users/UserResource.php` + `Pages/{ListUsers,CreateUser,EditUser}.php`
- Test: `tests/Feature/Filament/BlogCategoryResourceTest.php`
- Test: `tests/Feature/Filament/UserResourceTest.php`

**Interfaces:**
- Consumes: `AjayDhakal\FilamentStory\Models\BlogCategory` (plugin), `App\Models\User`.
- Produces: `BlogCategoryResource::getUrl()` and `UserResource::getUrl()` for `AdminNavigation` (Task 14).
- `blog_categories` columns are `name` and `slug`. The model auto-generates a unique slug in its `booted()` saving hook, so the form must **not** require `slug` — leave it out entirely and let the model fill it.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Filament/BlogCategoryResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(BlogCategoryResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_category_and_the_model_generates_the_slug(): void
    {
        Livewire::test(CreateBlogCategory::class)
            ->fillForm(['name' => 'District News'])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = BlogCategory::query()->sole();

        $this->assertSame('District News', $category->name);
        $this->assertNotEmpty($category->slug);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateBlogCategory::class)
            ->fillForm(['name' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }
}
```

Create `tests/Feature/Filament/UserResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_user_with_a_hashed_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Laurentius',
                'email' => 'new@storeframe.io',
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new@storeframe.io')->sole();

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@storeframe.io']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'taken@storeframe.io', 'password' => 'secret-password'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Filament/BlogCategoryResourceTest.php tests/Feature/Filament/UserResourceTest.php`
Expected: FAIL — resource classes not found.

- [ ] **Step 3: Generate the scaffolds**

```bash
php artisan make:filament-resource BlogCategory --panel=admin --model-namespace="AjayDhakal\FilamentStory\Models"
php artisan make:filament-resource User --panel=admin
```

If the `--model-namespace` flag is unavailable on this build, generate with the default and correct the `$model` import by hand.

- [ ] **Step 4: Write `BlogCategoryResource`**

```php
<?php

namespace App\Filament\Resources\BlogCategories;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('The URL slug is generated automatically.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->color('gray'),
                TextColumn::make('posts_count')->counts('posts')->label('Posts'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogCategories::route('/'),
            'create' => Pages\CreateBlogCategory::route('/create'),
            'edit' => Pages\EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Write `UserResource`**

```php
<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                // Leaving the field blank on edit must not blank the password.
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8)
                ->helperText('Leave blank when editing to keep the current password.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Joined'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 6: Run the tests and commit**

Run: `php artisan test tests/Feature/Filament/BlogCategoryResourceTest.php tests/Feature/Filament/UserResourceTest.php`
Expected: PASS — 6 tests.

```bash
git add app/Filament/Resources/BlogCategories app/Filament/Resources/Users tests/Feature/Filament
git commit -m "feat: add blog category and user resources"
```

---

### Task 14: `AdminNavigation` — the curated sidebar

This is the task that makes the sidebar comprehensive. Until now every resource has been auto-registered wherever the plugins decided; this replaces all of that with one explicit tree.

**Files:**
- Create: `app/Filament/Navigation/AdminNavigation.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php:29-49`
- Test: `tests/Feature/Filament/AdminNavigationTest.php`

**Interfaces:**
- Consumes: `HomepageEditor` (Task 10), `SiteSettingsPage` (Task 11), the four ordered resources (Task 12), `BlogCategoryResource` and `UserResource` (Task 13), plus the three plugin resources:
  - `CybertronianKelvin\Graper\Resources\GraperPageResource`
  - `AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource`
  - `Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource`
- Produces: `App\Filament\Navigation\AdminNavigation::build(NavigationBuilder $builder): NavigationBuilder`.

**Why a builder rather than per-resource properties:** `NavigationManager::get()` early-returns `$this->panel->buildNavigation()` at `vendor/filament/filament/src/Navigation/NavigationManager.php:49-50`, bypassing auto-registration entirely. That is what lets us override the plugins' hardcoded placement (`BlogPostResource.php:28` pins itself to a group called `Blogs`; `GraperPageResource.php:32` sets label `Pages` with no group) without reflection or vendor patching.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/AdminNavigationTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\User;
use CybertronianKelvin\Graper\Resources\GraperPageResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<int, string>
     */
    private function groupLabels(): array
    {
        return array_values(array_filter(array_map(
            fn ($group) => $group->getLabel(),
            Filament::getNavigation(),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function itemLabels(): array
    {
        $labels = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();
            }
        }

        return $labels;
    }

    public function test_it_registers_the_five_content_groups(): void
    {
        $this->assertSame(
            ['Content', 'Homepage Data', 'Appearance', 'Settings', 'System'],
            array_values(array_intersect(
                ['Content', 'Homepage Data', 'Appearance', 'Settings', 'System'],
                $this->groupLabels(),
            )),
        );
    }

    public function test_it_lists_every_expected_item(): void
    {
        $labels = $this->itemLabels();

        foreach ([
            'Dashboard', 'Homepage', 'Pages', 'Blog Posts', 'Blog Categories',
            'District Places', 'Facilities', 'Stats',
            'Public Menu', 'Admin Topbar Menu',
            'Site Settings', 'Users',
        ] as $expected) {
            $this->assertContains($expected, $labels, "Missing sidebar item: {$expected}");
        }
    }

    public function test_no_item_appears_twice(): void
    {
        $labels = $this->itemLabels();

        $this->assertSame(
            count($labels),
            count(array_unique($labels)),
            // NOTE: duplicates are structurally impossible — NavigationManager::get() early-returns the
            // builder's output, so auto-registration and the builder are never both active. This is kept as a
            // cheap invariant guard in case a future Filament version merges the two paths. It is NOT the guard
            // against the plugins' hardcoded placement: the group/label/badge assertions are.
            'A duplicate sidebar item would mean auto-registration is running alongside the navigation builder.',
        );
    }

    public function test_the_topbar_menu_is_relabelled_to_avoid_confusion_with_the_public_menu(): void
    {
        $labels = $this->itemLabels();

        $this->assertContains('Admin Topbar Menu', $labels);
        $this->assertNotContains('Topbar Menu', $labels);
    }

    public function test_the_pages_item_points_at_the_graper_resource(): void
    {
        $urls = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $urls[$item->getLabel()] = $item->getUrl();
            }
        }

        $this->assertSame(GraperPageResource::getUrl('index'), $urls['Pages']);
    }

    public function test_the_blog_posts_badge_counts_drafts_and_scheduled_posts(): void
    {
        BlogPost::factory()->count(2)->create(['status' => BlogPost::STATUS_DRAFT]);
        BlogPost::factory()->create(['status' => BlogPost::STATUS_PUBLISHED]);

        $badge = null;

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'Blog Posts') {
                    $badge = $item->getBadge();
                }
            }
        }

        $this->assertSame('2', (string) $badge);
    }
}
```

If Story ships no `BlogPost` factory, replace `BlogPost::factory()->...` with direct `BlogPost::create([...])` calls supplying `title`, `slug`, `content` and `status`. Check `vendor/ajaydhakal/filament-story/database/factories` first.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Filament/AdminNavigationTest.php`
Expected: FAIL — groups such as `Homepage Data` are absent, and `Topbar Menu` still appears under the plugin's own label.

- [ ] **Step 3: Write `AdminNavigation`**

Create `app/Filament/Navigation/AdminNavigation.php`:

```php
<?php

namespace App\Filament\Navigation;

use AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Filament\Pages\HomepageEditor;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use App\Filament\Resources\Stats\StatResource;
use App\Filament\Resources\Users\UserResource;
use CybertronianKelvin\Graper\Resources\GraperPageResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

/**
 * The single owner of the admin sidebar.
 *
 * Using a NavigationBuilder makes Filament skip auto-registration entirely
 * (NavigationManager::get() early-returns at line 49), which is how the three
 * plugins' hardcoded navigation placement gets overridden without touching
 * vendor code.
 *
 * Consequence: any resource or page added later must be listed here or it will
 * not appear in the sidebar.
 */
final class AdminNavigation
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder
            ->items([
                NavigationItem::make('Dashboard')
                    ->icon('heroicon-o-home')
                    ->url(Dashboard::getUrl())
                    ->isActiveWhen(fn () => request()->routeIs(Dashboard::getRouteName()))
                    ->sort(0),
            ])
            ->groups([
                NavigationGroup::make('Content')
                    ->items([
                        NavigationItem::make('Homepage')
                            ->icon('heroicon-o-home-modern')
                            ->url(HomepageEditor::getUrl())
                            ->isActiveWhen(fn () => request()->routeIs(HomepageEditor::getRouteName()))
                            ->sort(1),
                        ...self::resourceItems(GraperPageResource::class, 'Pages', 'heroicon-o-document-duplicate', 2),
                        ...self::resourceItems(BlogPostResource::class, 'Blog Posts', 'heroicon-o-newspaper', 3, self::pendingPostCount()),
                        ...self::resourceItems(BlogCategoryResource::class, 'Blog Categories', 'heroicon-o-tag', 4),
                    ]),

                NavigationGroup::make('Homepage Data')
                    ->items([
                        ...self::resourceItems(DistrictPlaceResource::class, 'District Places', 'heroicon-o-building-office-2', 1),
                        ...self::resourceItems(FacilityResource::class, 'Facilities', 'heroicon-o-wrench-screwdriver', 2),
                        ...self::resourceItems(StatResource::class, 'Stats', 'heroicon-o-chart-bar', 3),
                    ]),

                NavigationGroup::make('Appearance')
                    ->items([
                        ...self::resourceItems(PublicMenuItemResource::class, 'Public Menu', 'heroicon-o-link', 1),
                        // Deliberately NOT "Topbar Menu": this renders inside the
                        // admin panel's topbar, not on the public site. Sitting
                        // next to "Public Menu" the shorter label misleads.
                        ...self::resourceItems(TopbarMenuItemResource::class, 'Admin Topbar Menu', 'heroicon-o-bars-3-bottom-left', 2),
                    ]),

                NavigationGroup::make('Settings')
                    ->items([
                        NavigationItem::make('Site Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->url(SiteSettingsPage::getUrl())
                            ->isActiveWhen(fn () => request()->routeIs(SiteSettingsPage::getRouteName()))
                            ->sort(1),
                    ]),

                NavigationGroup::make('System')
                    ->items([
                        ...self::resourceItems(UserResource::class, 'Users', 'heroicon-o-users', 1),
                    ]),
            ]);
    }

    /**
     * Builds one navigation item for a resource, labelled and sorted by us
     * rather than by whatever the resource class hardcodes.
     *
     * @param  class-string  $resource
     * @return array<int, NavigationItem>
     */
    private static function resourceItems(
        string $resource,
        string $label,
        string $icon,
        int $sort,
        ?string $badge = null,
    ): array {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->url($resource::getUrl('index'))
            ->isActiveWhen(fn () => request()->routeIs($resource::getRouteBaseName().'.*'))
            ->sort($sort);

        if ($badge !== null) {
            $item->badge($badge, 'warning');
        }

        return [$item];
    }

    /**
     * Posts still needing attention: drafts plus anything scheduled but not yet
     * published. Returns null when there are none, so no badge renders.
     */
    private static function pendingPostCount(): ?string
    {
        $count = BlogPost::query()
            ->whereIn('status', [BlogPost::STATUS_DRAFT, BlogPost::STATUS_SCHEDULED])
            ->count();

        return $count > 0 ? (string) $count : null;
    }
}
```

- [ ] **Step 4: Wire it into the panel**

In `app/Providers/Filament/AdminPanelProvider.php`, add the import `use App\Filament\Navigation\AdminNavigation;` and `use Filament\Navigation\NavigationBuilder;`, then add this call to the `$panel` chain immediately after `->colors([...])`:

```php
            ->navigation(fn (NavigationBuilder $builder) => AdminNavigation::build($builder))
```

Leave `->discoverResources()`, `->discoverPages()` and the three `->plugin()` calls exactly as they are. Discovery still registers the routes; the builder only controls what the sidebar shows.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Filament/AdminNavigationTest.php`
Expected: PASS — 6 tests. If `getRouteBaseName()` is not a static method on this Filament build, substitute `$resource::getRouteBaseName()`'s equivalent from the generated resource scaffolds, or simplify `isActiveWhen` to compare `request()->url()` against the item URL prefix.

- [ ] **Step 6: Verify in a browser and commit**

```bash
php artisan test
```

Expected: the whole suite passes. Then load `http://iat-cms.test/superduper` and confirm the five groups appear in order with no duplicate entries and no stray `Blogs` group.

```bash
git add app/Filament/Navigation/AdminNavigation.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/Filament/AdminNavigationTest.php
git commit -m "feat: replace plugin sidebar placement with a curated navigation tree"
```

---

### Task 15: `HomepageData`, i18n payload and `HomeController`

**Files:**
- Create: `app/Support/HomepageData.php`
- Create: `app/Http/Controllers/HomeController.php`
- Modify: `routes/web.php`
- Delete: `resources/views/welcome.blade.php`
- Test: `tests/Feature/HomepageDataTest.php`

**Interfaces:**
- Consumes: all six models (Tasks 3–6), `AjayDhakal\FilamentStory\Models\BlogPost`.
- Produces:
  - `App\Support\HomepageData` — `final readonly class` with public promoted properties `content`, `settings`, `menu`, `cta`, `places`, `facilities`, `stats`, `posts`, `i18n`.
  - `HomepageData::build(): self`.
  - `HomepageData::I18N_MAP` — `array<string, string>` mapping the reference's `data-i18n` keys to `HomepageContent` column names.
  - `$i18n` shape: `array<string locale, array<string key, string html>>`. Values are HTML-escaped with newlines converted to `<br>`, because the char-split animation splits headings on `<br>`.
  - `App\Http\Controllers\HomeController::__invoke(): View` returning view `home` with `data`.
- `BlogPost` has **no** `published` query scope. Filter with `->where('status', BlogPost::STATUS_PUBLISHED)` explicitly.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HomepageDataTest.php`:

```php
<?php

namespace Tests\Feature;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\Stat;
use App\Support\HomepageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_on_a_completely_empty_database(): void
    {
        $data = HomepageData::build();

        $this->assertInstanceOf(HomepageContent::class, $data->content);
        $this->assertTrue($data->menu->isEmpty());
        $this->assertTrue($data->places->isEmpty());
        $this->assertNull($data->cta);
    }

    public function test_it_separates_nav_links_from_the_cta(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Leasing enquiry'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);

        $data = HomepageData::build();

        $this->assertCount(1, $data->menu);
        $this->assertSame('Leasing enquiry', $data->cta->t('label', 'en'));
    }

    public function test_it_excludes_inactive_places_and_respects_order(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Second'], 'sort' => 2]);
        DistrictPlace::create(['title' => ['en' => 'First'], 'sort' => 1]);
        DistrictPlace::create(['title' => ['en' => 'Hidden'], 'sort' => 3, 'is_active' => false]);

        $titles = HomepageData::build()->places->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['First', 'Second'], $titles->all());
    }

    public function test_it_takes_at_most_three_published_posts_newest_first(): void
    {
        BlogPost::create(['title' => 'Oldest', 'slug' => 'oldest', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDays(5)]);
        BlogPost::create(['title' => 'Newest', 'slug' => 'newest', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()]);
        BlogPost::create(['title' => 'Middle', 'slug' => 'middle', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);
        BlogPost::create(['title' => 'Fourth', 'slug' => 'fourth', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDays(9)]);
        BlogPost::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT]);

        $titles = HomepageData::build()->posts->pluck('title');

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $titles->all());
    }

    public function test_the_i18n_payload_covers_all_three_locales(): void
    {
        HomepageContent::singleton()->update([
            'hero_line' => ['en' => 'English', 'id' => 'Indonesia', 'cn' => '中文'],
        ]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame(['en', 'id', 'cn'], array_keys($i18n));
        $this->assertSame('English', $i18n['en']['heroline']);
        $this->assertSame('Indonesia', $i18n['id']['heroline']);
        $this->assertSame('中文', $i18n['cn']['heroline']);
    }

    public function test_the_payload_falls_back_to_english_per_key(): void
    {
        HomepageContent::singleton()->update([
            'hero_line' => ['en' => 'English line'],
            'contact_heading' => ['en' => 'Contact', 'id' => 'Kontak'],
        ]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame('English line', $i18n['cn']['heroline']);
        $this->assertSame('Kontak', $i18n['id']['contacth']);
    }

    public function test_newlines_become_br_tags_for_the_char_split(): void
    {
        HomepageContent::singleton()->update(['hero_line' => ['en' => "A district\nthat never\nclocks out"]]);

        $this->assertSame('A district<br>that never<br>clocks out', HomepageData::build()->i18n['en']['heroline']);
    }

    public function test_the_payload_escapes_html(): void
    {
        HomepageContent::singleton()->update(['hero_sub' => ['en' => '<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>', HomepageData::build()->i18n['en']['herosub']);
    }

    public function test_the_payload_includes_nav_links_and_the_cta(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'District', 'id' => 'Kawasan'], 'url' => '#district', 'sort' => 2]);
        PublicMenuItem::create(['label' => ['en' => 'Leasing enquiry', 'id' => 'Ajukan sewa'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame('Perusahaan', $i18n['id']['nav1']);
        $this->assertSame('Kawasan', $i18n['id']['nav2']);
        $this->assertSame('Ajukan sewa', $i18n['id']['cta']);
    }

    public function test_the_controller_returns_the_home_view_with_the_dto(): void
    {
        // The `home` view does not exist until Task 16, so assert on the
        // controller's return value rather than rendering the response. This
        // keeps Task 15 independently green.
        $view = (new \App\Http\Controllers\HomeController)();

        $this->assertSame('home', $view->name());
        $this->assertInstanceOf(HomepageData::class, $view->getData()['data']);
    }

    public function test_the_route_is_registered_and_points_at_the_controller(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('home');

        $this->assertNotNull($route);
        $this->assertSame('/', $route->uri());
        $this->assertSame(\App\Http\Controllers\HomeController::class, $route->getActionName());
    }
}
```

The two tests above deliberately avoid `$this->get('/')`. The `home` view arrives in Task 16, and a task must not end with a red suite — asserting on the controller's returned `View` object and the route registration verifies everything Task 15 actually owns.

Remove the now-unused `Facility` and `Stat` imports from this test file if your editor flags them; `Stat` is still used by `test_it_excludes_inactive_places_and_respects_order`'s neighbours, so check before deleting.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/HomepageDataTest.php`
Expected: FAIL — `Class "App\Support\HomepageData" not found`.

- [ ] **Step 3: Write `HomepageData`**

Create `app/Support/HomepageData.php`:

```php
<?php

namespace App\Support;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\Stat;
use Illuminate\Support\Collection;

/**
 * Everything the homepage needs, assembled once.
 *
 * Blade performs no queries, which keeps page assembly a single testable step.
 */
final readonly class HomepageData
{
    /**
     * The reference markup's `data-i18n` keys mapped to HomepageContent columns.
     * Nav keys (`nav1`..`navN`) and `cta` come from PublicMenuItem instead.
     *
     * @var array<string, string>
     */
    public const I18N_MAP = [
        'brandsub' => 'brand_sub',
        'heroline' => 'hero_line',
        'herosub' => 'hero_sub',
        'abouth' => 'about_heading',
        'aboutp' => 'about_body',
        'aboutcta' => 'about_cta_label',
        'disth' => 'district_heading',
        'distp' => 'district_body',
        'fach' => 'facilities_heading',
        'facp' => 'facilities_body',
        'newsh' => 'news_heading',
        'newscta' => 'news_cta_label',
        'contacth' => 'contact_heading',
        'marquee' => 'marquee_text',
    ];

    /**
     * @param  Collection<int, PublicMenuItem>  $menu
     * @param  Collection<int, DistrictPlace>  $places
     * @param  Collection<int, Facility>  $facilities
     * @param  Collection<int, Stat>  $stats
     * @param  Collection<int, BlogPost>  $posts
     * @param  array<string, array<string, string>>  $i18n
     */
    public function __construct(
        public HomepageContent $content,
        public SiteSetting $settings,
        public Collection $menu,
        public ?PublicMenuItem $cta,
        public Collection $places,
        public Collection $facilities,
        public Collection $stats,
        public Collection $posts,
        public array $i18n,
    ) {}

    public static function build(): self
    {
        $content = HomepageContent::singleton();
        $menu = PublicMenuItem::query()->links()->get();
        $cta = PublicMenuItem::query()->cta()->first();

        return new self(
            content: $content,
            settings: SiteSetting::singleton(),
            menu: $menu,
            cta: $cta,
            places: DistrictPlace::query()->active()->ordered()->get(),
            facilities: Facility::query()->active()->ordered()->get(),
            stats: Stat::query()->ordered()->get(),
            posts: BlogPost::query()
                ->where('status', BlogPost::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
            i18n: self::i18nPayload($content, $menu, $cta),
        );
    }

    /**
     * @param  Collection<int, PublicMenuItem>  $menu
     * @return array<string, array<string, string>>
     */
    private static function i18nPayload(HomepageContent $content, Collection $menu, ?PublicMenuItem $cta): array
    {
        $payload = [];

        foreach (array_keys(SiteSetting::LOCALES) as $locale) {
            $bucket = [];

            foreach (self::I18N_MAP as $key => $column) {
                $bucket[$key] = self::html($content->t($column, $locale));
            }

            foreach ($menu->values() as $index => $item) {
                $bucket['nav'.($index + 1)] = self::html($item->t('label', $locale));
            }

            if ($cta !== null) {
                $bucket['cta'] = self::html($cta->t('label', $locale));
            }

            $payload[$locale] = $bucket;
        }

        return $payload;
    }

    /**
     * Escape first, then turn newlines into the `<br>` tags the char-split
     * animation splits headings on.
     */
    private static function html(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace(["\r\n", "\n", "\r"], '<br>', e($value));
    }
}
```

- [ ] **Step 4: Write the controller and route**

Create `app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\HomepageData;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', ['data' => HomepageData::build()]);
    }
}
```

Replace the closure route in `routes/web.php`:

```php
<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
```

Then delete the stock welcome view:

```bash
rm resources/views/welcome.blade.php
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/HomepageDataTest.php`
Expected: PASS — all 11 tests. The suite must be fully green before committing; nothing here renders the `home` view, which arrives in Task 16.

Then confirm the whole suite is still green:

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/HomepageData.php app/Http/Controllers/HomeController.php routes/web.php tests/Feature/HomepageDataTest.php
git rm resources/views/welcome.blade.php
git commit -m "feat: add homepage view model with trilingual i18n payload"
```

---

### Task 16: Blade layout and section partials

This task is a **transcription** job, not a design job. The markup already exists in the reference; the work is porting it into partials and replacing hardcoded strings with `$data` bindings. Preserve every inline `style` attribute and every class name exactly — they are the design.

**Source of truth:** `<SCBD>/shell.html` where `<SCBD>` is the extracted-assets directory from Global Constraints. Section byte offsets in that file, to locate each block:

| Block | Offset | Notes |
|---|---|---|
| loader | 17849 | `[data-loader]`, `[data-loader-num]`, `[data-loader-bar]` |
| header | 18710 | `header[data-header]`, `[data-navlink]`, `[data-lang]` |
| hero (`#top`) | 21152 | `[data-split]`, `[data-parallax-wrap]`, `[data-parallax]` |
| marquee | 22812 | `[data-marquee]` |
| about (`#about`) | 23380 | `[data-fade]`, `[data-count]` stats live here |
| district (`#district`) | 26724 | `[data-horizontal]`, `[data-horizontal-track]` |
| facilities (`#facilities`) | 30742 | `[data-stack]`, `[data-card]` |
| news (`#news`) | 35332 | `[data-news]` rows |
| contact (`#contact`) | 38615 | `[data-split]` heading |

There is **no** `<footer>` element in the reference — the page ends at `#contact`. Do not invent one.

**Files:**
- Create: `resources/views/components/layouts/public.blade.php`
- Create: `resources/views/home.blade.php`
- Create: `resources/views/partials/home/{loader,header,hero,marquee,about,district,facilities,news,contact}.blade.php`
- Test: `tests/Feature/HomepageRenderTest.php`

**Interfaces:**
- Consumes: `HomepageData` (Task 15) as `$data`; `resources/css/scbd.css` and `resources/js/scbd/index.js` (Task 1) via `@vite`.
- Produces: a `home` view emitting every `data-*` attribute the animation layer (Task 17) queries, plus `<script type="application/json" id="scbd-i18n">` carrying `$data->i18n`.

**Binding contract — every partial must satisfy this:**

| Partial | Required attributes | Bindings |
|---|---|---|
| loader | `[data-loader]`, `[data-loader-num]`, `[data-loader-bar]` | none (static chrome) |
| header | `header[data-header]`, `[data-navlink]` per link, `[data-lang]` per locale with `data-lang="{code}"`, `[data-magnetic]` on the CTA | `$data->menu` → nav links with `data-i18n="nav{{ $loop->iteration }}"`; `$data->cta` → CTA with `data-i18n="cta"`; `$data->content->t('brand_sub')` with `data-i18n="brandsub"`; locale buttons from `App\Models\SiteSetting::LOCALES` |
| hero | `#top`, `[data-split] [data-i18n="heroline"]`, `[data-i18n="herosub"]`, `[data-parallax-wrap]`, `img[data-parallax]` | `hero_line`, `hero_sub`, `hero_image` |
| marquee | `[data-marquee]` | `marquee_text` with `data-i18n="marquee"`, repeated 4× inside the track so the `-50%` loop is seamless |
| about | `#about`, `[data-fade]`, one `[data-count]` per stat, `[data-reveal]` on the image | `about_heading` (`abouth`, `data-split` not required), `about_body` (`aboutp`), `about_cta_label` (`aboutcta`) + `about_cta_url`, `about_image`, `$data->stats` |
| district | `#district`, `[data-horizontal]`, `[data-horizontal-track]`, `img` per place | `district_heading` (`disth`, `data-split`), `district_body` (`distp`), `$data->places`, `contact_address` in the location panel |
| facilities | `#facilities`, `[data-stack]`, `article[data-card]` per facility | `facilities_heading` (`fach`, `data-split`), `facilities_body` (`facp`), `$data->facilities` |
| news | `#news`, `[data-news]` per row | `news_heading` (`newsh`, `data-split`), `news_cta_label` (`newscta`), `$data->posts` |
| contact | `#contact`, `[data-split] [data-i18n="contacth"]` | `contact_heading`, `contact_email`, `contact_phone`, `contact_address` |

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HomepageRenderTest.php`:

```php
<?php

namespace Tests\Feature;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function seedMinimum(): void
    {
        HomepageContent::singleton()->update([
            'brand_sub' => ['en' => 'Danayasa Arthatama'],
            'hero_line' => ['en' => "A district\nthat never\nclocks out"],
            'hero_sub' => ['en' => 'Forty-five hectares.'],
            'district_heading' => ['en' => "Everything inside\none walk"],
            'facilities_heading' => ['en' => "Services that\nrun underneath"],
            'news_heading' => ['en' => "Latest from\nthe district"],
            'contact_heading' => ['en' => "Take an address\nin the district"],
            'marquee_text' => ['en' => 'Offices — Hotels'],
            'contact_address' => "Jl. Jenderal\nSudirman",
        ]);
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Leasing enquiry'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);
        DistrictPlace::create(['title' => ['en' => 'The towers'], 'caption' => ['en' => 'Grade A office'], 'sort' => 1]);
        Facility::create(['title' => ['en' => 'District clinic'], 'body' => ['en' => 'On-site care.'], 'sort' => 1]);
        Stat::create(['label' => ['en' => 'Hectares'], 'value' => 45, 'sort' => 1]);
    }

    public function test_all_animation_hooks_are_present(): void
    {
        $this->seedMinimum();

        $response = $this->get('/');

        foreach ([
            'data-loader', 'data-loader-num', 'data-loader-bar',
            'data-header', 'data-navlink', 'data-lang',
            'data-split', 'data-parallax-wrap', 'data-parallax',
            'data-marquee', 'data-fade', 'data-reveal', 'data-count',
            'data-horizontal-track', 'data-stack', 'data-card',
            'data-news', 'data-cursor', 'data-cursor-ring', 'data-magnetic',
        ] as $hook) {
            $response->assertSee($hook, false);
        }
    }

    public function test_all_sections_are_present(): void
    {
        $this->seedMinimum();

        $response = $this->get('/');

        foreach (['id="top"', 'id="about"', 'id="district"', 'id="facilities"', 'id="news"', 'id="contact"'] as $section) {
            $response->assertSee($section, false);
        }
    }

    public function test_headings_emit_br_separated_lines_for_the_char_split(): void
    {
        $this->seedMinimum();

        $this->get('/')->assertSee('A district<br>that never<br>clocks out', false);
    }

    public function test_the_i18n_payload_is_embedded(): void
    {
        $this->seedMinimum();

        $this->get('/')
            ->assertSee('id="scbd-i18n"', false)
            ->assertSee('heroline', false);
    }

    public function test_nav_links_are_numbered_for_the_switcher(): void
    {
        $this->seedMinimum();

        $this->get('/')
            ->assertSee('data-i18n="nav1"', false)
            ->assertSee('data-i18n="cta"', false);
    }

    public function test_empty_sections_are_skipped(): void
    {
        HomepageContent::singleton();

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee('data-horizontal-track', false)
            ->assertDontSee('data-stack', false);
    }

    public function test_it_renders_published_posts_in_the_news_section(): void
    {
        $this->seedMinimum();
        BlogPost::create([
            'title' => 'Eco Enzyme as part of household waste management',
            'slug' => 'eco-enzyme',
            'content' => 'x',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/')->assertSee('Eco Enzyme as part of household waste management');
    }

    public function test_a_missing_image_does_not_emit_an_empty_src(): void
    {
        $this->seedMinimum();

        $this->get('/')->assertDontSee('src=""', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/HomepageRenderTest.php`
Expected: FAIL — `View [home] not found`.

- [ ] **Step 3: Write the layout**

Create it as an **anonymous Blade component** at `resources/views/components/layouts/public.blade.php` — that path is what makes `<x-layouts.public>` resolve in Step 4. (An earlier draft of this plan offered `resources/views/layouts/public.blade.php` with `@extends` as an alternative; the component path is the one to use, so the choice is settled here rather than left to the implementer.)

```blade
<!DOCTYPE html>
<html lang="{{ $data->settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $data->settings->t('meta_title') ?? ($data->settings->site_name ?? config('app.name')) }}</title>
    <meta name="description" content="{{ $data->settings->t('meta_description') }}">

    @if ($data->settings->favicon)
        <link rel="icon" href="{{ Storage::disk('public')->url($data->settings->favicon) }}">
    @endif

    @vite(['resources/css/scbd.css', 'resources/js/scbd/index.js'])
</head>
<body>
    {{-- Custom cursor. Hidden on coarse pointers by the reference stylesheet. --}}
    <div class="scbd-cursor" data-cursor style="position:fixed;top:0;left:0;width:7px;height:7px;border-radius:50%;background:#ec3013;pointer-events:none;z-index:9999;transform:translate(-50%,-50%);"></div>
    <div class="scbd-cursor" data-cursor-ring style="position:fixed;top:0;left:0;width:34px;height:34px;border-radius:50%;border:1px solid rgba(32,30,29,0.45);pointer-events:none;z-index:9998;transform:translate(-50%,-50%);"></div>

    {{ $slot }}

    {{-- Translation payload consumed by resources/js/scbd/i18n.js --}}
    <script type="application/json" id="scbd-i18n">@json($data->i18n)</script>
</body>
</html>
```

- [ ] **Step 4: Write `home.blade.php`**

Section order is load-bearing: the loader hands off to `#top`, and `#district` pins the viewport. Do not reorder.

```blade
<x-layouts.public :data="$data">
    @include('partials.home.loader')
    @include('partials.home.header', ['data' => $data])

    <main style="position:relative;">
        @include('partials.home.hero', ['data' => $data])
        @include('partials.home.marquee', ['data' => $data])
        @include('partials.home.about', ['data' => $data])
        @include('partials.home.district', ['data' => $data])
        @include('partials.home.facilities', ['data' => $data])
        @include('partials.home.news', ['data' => $data])
        @include('partials.home.contact', ['data' => $data])
    </main>
</x-layouts.public>
```

`<x-layouts.public>` resolves to `resources/views/components/layouts/public.blade.php` from Step 3. The `:data="$data"` attribute makes `$data` available inside the layout for the `<title>`, meta tags and the i18n payload.

- [ ] **Step 5: Write the hero partial as the worked example**

Create `resources/views/partials/home/hero.blade.php`. This is the pattern every other partial follows — transcribe the reference block, then substitute bindings:

```blade
@php
    $heroLine = $data->i18n[$data->settings->default_locale ?? 'en']['heroline'] ?? '';
@endphp

<section id="top" style="padding:160px 60px 90px;position:relative;">
    <div style="max-width:1320px;margin:0 auto;display:grid;grid-template-columns:1.1fr 0.9fr;gap:64px;align-items:end;">
        <div>
            <h1 data-split
                data-i18n="heroline"
                style="font-family:Archivo,sans-serif;font-weight:800;font-size:clamp(44px,7vw,104px);line-height:0.94;letter-spacing:-0.03em;color:#201e1d;margin:0;">{!! $heroLine !!}</h1>

            <p data-i18n="herosub"
               style="margin:32px 0 0;max-width:52ch;font-size:17px;line-height:1.6;color:#201e1d;opacity:0.72;">{{ $data->content->t('hero_sub') }}</p>
        </div>

        <div data-parallax-wrap style="overflow:hidden;clip-path:inset(0% 0% 0% 0%);">
            @if ($data->content->hero_image)
                <img data-parallax
                     src="{{ Storage::disk('public')->url($data->content->hero_image) }}"
                     alt=""
                     style="width:100%;height:520px;object-fit:cover;display:block;transform:scale(1.14);">
            @else
                <div data-parallax style="width:100%;height:520px;background:#201e1d;opacity:0.08;"></div>
            @endif
        </div>
    </div>
</section>
```

Three rules this demonstrates, to apply everywhere:

1. **Headings use `{!! !!}`, body copy uses `{{ }}`.** Heading values come from `$data->i18n`, which is already escaped and carries `<br>` tags — double-escaping would print the tags. Everything else goes through normal escaping.
2. **Every image is guarded.** `@if ($model->image)` with a neutral coloured block in the `@else`, so a missing upload never produces `src=""`.
3. **The `data-i18n` key must match `HomepageData::I18N_MAP`** or the language switcher silently skips that element.

- [ ] **Step 6: Write the news partial as the second worked example**

Create `resources/views/partials/home/news.blade.php` — the loop-over-relations pattern:

```blade
@if ($data->posts->isNotEmpty())
    @php
        $newsHeading = $data->i18n[$data->settings->default_locale ?? 'en']['newsh'] ?? '';
    @endphp

    <section id="news" style="padding:120px 60px;">
        <div style="max-width:1320px;margin:0 auto;">
            <div style="display:flex;justify-content:space-between;align-items:end;gap:32px;margin-bottom:56px;">
                <h2 data-split data-i18n="newsh"
                    style="font-family:Archivo,sans-serif;font-weight:800;font-size:clamp(32px,4vw,64px);line-height:1;letter-spacing:-0.02em;color:#201e1d;margin:0;">{!! $newsHeading !!}</h2>

                <a href="{{ route('filament-story.index') }}"
                   data-i18n="newscta"
                   data-magnetic
                   style="font-size:14px;font-weight:600;white-space:nowrap;">{{ $data->content->t('news_cta_label') ?? 'All news' }}</a>
            </div>

            <div>
                @foreach ($data->posts as $post)
                    <a href="{{ route('filament-story.show', $post->slug) }}"
                       data-news
                       style="display:grid;grid-template-columns:120px 1fr;gap:32px;align-items:baseline;padding:28px 0;border-top:1px solid rgba(32,30,29,0.14);color:#201e1d;text-decoration:none;">
                        <time datetime="{{ $post->published_at?->toDateString() }}"
                              style="font-size:13px;opacity:0.55;">{{ $post->published_at?->format('d.m.y') }}</time>
                        <span style="font-size:clamp(18px,2vw,26px);font-weight:600;line-height:1.3;">{{ $post->title }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
```

Route names come from Story's own route group (`filament-story.index`, `filament-story.show`), so the news section links into the plugin's shipped blog views rather than a route we invent.

- [ ] **Step 7: Transcribe the remaining seven partials**

For each of `loader`, `header`, `marquee`, `about`, `district`, `facilities`, `contact`: open `<SCBD>/shell.html` at the offset in the table above, copy the block verbatim into the partial, then apply the binding contract from the table and the three rules from Step 5. Wrap `district`, `facilities` and `about`'s stat row in `@if (...->isNotEmpty())` so empty collections skip the section — `test_empty_sections_are_skipped` depends on this.

Two specifics worth calling out:

- **header** — render one `[data-lang]` button per `SiteSetting::LOCALES` entry, with `data-lang="{{ $code }}"` and the English button styled active (`background:#201e1d;color:#f3f2f2`), matching what `i18n.js` toggles. Nav links get `data-i18n="nav{{ $loop->iteration }}"`, numbered in `$data->menu` order.
- **marquee** — repeat `{{ $data->content->t('marquee_text') }} — ` four times inside `[data-marquee]`. The animation translates the track by `-50%`, so the content must be at least double the viewport width to loop seamlessly.

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/HomepageRenderTest.php tests/Feature/HomepageDataTest.php`
Expected: PASS — all tests including the two route tests that were failing at the end of Task 15.

- [ ] **Step 9: Commit**

```bash
npm run build
git add resources/views tests/Feature/HomepageRenderTest.php
git commit -m "feat: port SCBD homepage markup to Blade partials"
```

---

### Task 17: Animation layer

Ported from `<SCBD>/page.jsx` (239 lines) into focused ES modules. Four deliberate changes from the reference, each explained in the code.

**Files:**
- Create: `resources/js/scbd/motion.js`
- Create: `resources/js/scbd/smoothScroll.js`
- Create: `resources/js/scbd/textSplit.js`
- Create: `resources/js/scbd/loader.js`
- Create: `resources/js/scbd/header.js`
- Create: `resources/js/scbd/reveal.js`
- Create: `resources/js/scbd/marquee.js`
- Create: `resources/js/scbd/district.js`
- Create: `resources/js/scbd/stack.js`
- Create: `resources/js/scbd/counters.js`
- Create: `resources/js/scbd/cursor.js`
- Create: `resources/js/scbd/i18n.js`
- Modify: `resources/js/scbd/index.js` (replacing the Task 1 placeholder)

**Interfaces:**
- Consumes: the `data-*` attributes emitted in Task 16; `gsap`, `gsap/ScrollTrigger` and `lenis` from Task 1; the `#scbd-i18n` JSON payload from Task 16.
- Produces:
  - `motion.js` → `prefersReducedMotion(): boolean`
  - `smoothScroll.js` → `createSmoothScroll(ScrollTrigger, reduced): Lenis`
  - `textSplit.js` → `splitTargets(root?): void`, `splitElement(el): void`
  - `loader.js` → `runLoader(gsap, ScrollTrigger, lenis, reduced): void`
  - `header.js` → `initHeader(gsap, lenis): void`
  - `reveal.js` → `initReveals(gsap, ScrollTrigger): void`
  - `marquee.js` → `initMarquee(gsap, ScrollTrigger): void`
  - `district.js` → `initDistrict(gsap, ScrollTrigger): boolean` — false when pinning was skipped
  - `stack.js` → `initCardStack(gsap): void`
  - `counters.js` → `initCounters(gsap, ScrollTrigger): void`
  - `cursor.js` → `initCursor(gsap): void`
  - `i18n.js` → `initLanguageSwitcher(ScrollTrigger): void`
  - `index.js` → `initScbd(): void`, auto-invoked on `DOMContentLoaded`

- [ ] **Step 1: Write `motion.js` and `smoothScroll.js`**

```js
// resources/js/scbd/motion.js

/**
 * The reference had no reduced-motion handling at all, which makes the page
 * unusable for anyone with the preference set. Every module consults this.
 */
export function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
```

```js
// resources/js/scbd/smoothScroll.js
import Lenis from 'lenis';

export function createSmoothScroll(ScrollTrigger, reduced) {
  // Reduced motion still gets Lenis (for programmatic scrollTo) but with
  // smoothing disabled, so anchor links work without animated easing.
  const lenis = new Lenis(
    reduced
      ? { duration: 0, smoothWheel: false, lerp: 1 }
      : { duration: 1.15, smoothWheel: true, lerp: 0.09 },
  );

  lenis.on('scroll', ScrollTrigger.update);

  const raf = (time) => {
    lenis.raf(time);
    requestAnimationFrame(raf);
  };
  requestAnimationFrame(raf);

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (!target) return;
      event.preventDefault();
      lenis.scrollTo(target, { offset: -70 });
    });
  });

  return lenis;
}
```

- [ ] **Step 2: Write `textSplit.js`**

```js
// resources/js/scbd/textSplit.js

/**
 * Wraps each character of a `[data-split]` heading in its own inline-block span
 * so the loader and scroll reveals can stagger them. Lines are delimited by
 * <br> in the server-rendered markup.
 */
export function splitElement(element) {
  if (element._origHTML == null) element._origHTML = element.innerHTML;

  element.innerHTML = element._origHTML
    .split(/<br[^>]*>/i)
    .map((line) => {
      const chars = line
        .trim()
        .split('')
        .map(
          (char) =>
            `<span data-char style="display:inline-block;white-space:pre;transform:translateY(105%);">${
              char === ' ' ? '&nbsp;' : char
            }</span>`,
        )
        .join('');

      return `<span style="display:block;overflow:hidden;padding-bottom:0.06em;">${chars}</span>`;
    })
    .join('');
}

export function splitTargets(root = document) {
  root.querySelectorAll('[data-split]').forEach(splitElement);
}
```

- [ ] **Step 3: Write `loader.js`**

```js
// resources/js/scbd/loader.js

export function runLoader(gsap, ScrollTrigger, lenis, reduced) {
  const loader = document.querySelector('[data-loader]');
  const number = document.querySelector('[data-loader-num]');
  const bar = document.querySelector('[data-loader-bar]');

  if (!loader) return;

  const finish = () => {
    loader.style.display = 'none';
    lenis.start();
    ScrollTrigger.refresh();
  };

  // Reduced motion: skip the whole intro and jump to the resting state.
  if (reduced || !number || !bar) {
    gsap.set('#top [data-char]', { yPercent: 0 });
    gsap.set('#top [data-parallax-wrap]', { clipPath: 'inset(0% 0% 0% 0%)' });
    gsap.set('header[data-header]', { yPercent: 0 });
    finish();
    return;
  }

  lenis.stop();

  const counter = { value: 0 };
  const timeline = gsap.timeline();

  timeline
    .to(counter, {
      value: 100,
      duration: 1.9,
      ease: 'power2.inOut',
      onUpdate: () => {
        number.textContent = String(Math.round(counter.value)).padStart(3, '0');
      },
    }, 0)
    .to(bar, { width: '100%', duration: 1.9, ease: 'power2.inOut' }, 0)
    .to([number, bar.parentNode], { opacity: 0, duration: 0.35 }, 1.95)
    .to(loader, {
      yPercent: -100,
      duration: 0.9,
      ease: 'expo.inOut',
      onComplete: finish,
    }, 2.15)
    .fromTo('#top [data-char]',
      { yPercent: 105 },
      { yPercent: 0, duration: 0.85, stagger: 0.014, ease: 'expo.out' }, 2.5)
    .fromTo('#top [data-parallax-wrap]',
      { clipPath: 'inset(100% 0% 0% 0%)' },
      { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.1, ease: 'expo.out' }, 2.6)
    .fromTo('header[data-header]',
      { yPercent: -100 },
      { yPercent: 0, duration: 0.6, ease: 'power3.out' }, 2.8);

  // Failsafe from the reference: never trap the user behind the loader.
  const forceOpen = () => {
    if (loader.style.display === 'none') return;
    timeline.progress(1, false);
    finish();
  };

  setTimeout(forceOpen, 5000);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && gsap.ticker.frame < 5) forceOpen();
  });
}
```

- [ ] **Step 4: Write `header.js`, `reveal.js`, `marquee.js`, `stack.js`**

```js
// resources/js/scbd/header.js

export function initHeader(gsap, lenis) {
  const header = document.querySelector('[data-header]');
  if (!header) return;

  let previous = 0;

  lenis.on('scroll', ({ scroll }) => {
    const hide = scroll > 140 && scroll > previous;
    gsap.to(header, { yPercent: hide ? -100 : 0, duration: 0.4, ease: 'power3.out' });
    previous = scroll;
  });
}
```

```js
// resources/js/scbd/reveal.js

export function initReveals(gsap, ScrollTrigger) {
  const parallax = document.querySelector('[data-parallax]');
  const wrap = document.querySelector('[data-parallax-wrap]');

  if (parallax && wrap) {
    gsap.to(parallax, {
      yPercent: 14,
      ease: 'none',
      scrollTrigger: { trigger: wrap, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  }

  document.querySelectorAll('[data-fade]').forEach((element) => {
    gsap.from(element, {
      y: 34,
      opacity: 0,
      duration: 0.9,
      ease: 'expo.out',
      scrollTrigger: { trigger: element, start: 'top 88%' },
    });
  });

  document
    .querySelectorAll('[data-reveal], #district img, #facilities img')
    .forEach((element) => {
      gsap.fromTo(element,
        { clipPath: 'inset(0% 0% 100% 0%)', scale: 1.16 },
        {
          clipPath: 'inset(0% 0% 0% 0%)',
          scale: 1,
          duration: 1.2,
          ease: 'expo.out',
          scrollTrigger: { trigger: element, start: 'top 92%' },
        });
    });

  // The reference wrote `yPercert` here (page.jsx:95) — a silent no-op that left
  // the contact heading visible before its own reveal ran.
  const contactChars = document.querySelectorAll('#contact [data-char]');
  const contact = document.querySelector('#contact');

  if (!contact || contactChars.length === 0) return;

  if (contact.getBoundingClientRect().top < window.innerHeight * 0.7) {
    gsap.set(contactChars, { yPercent: 0 });
    return;
  }

  gsap.set(contactChars, { yPercent: 105 });

  ScrollTrigger.create({
    trigger: '#contact',
    start: 'top 70%',
    once: true,
    onEnter: () =>
      gsap.to(contactChars, { yPercent: 0, duration: 0.8, stagger: 0.01, ease: 'expo.out' }),
  });
}
```

```js
// resources/js/scbd/marquee.js

export function initMarquee(gsap, ScrollTrigger) {
  const marquee = document.querySelector('[data-marquee]');
  if (!marquee) return;

  gsap.to(marquee, { xPercent: -50, duration: 26, ease: 'none', repeat: -1 });

  ScrollTrigger.create({
    trigger: marquee,
    start: 'top bottom',
    end: 'bottom top',
    onUpdate: (self) => {
      const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 900, 3);
      gsap.to(marquee, { timeScale: boost, duration: 0.3, overwrite: true });
    },
  });
}
```

```js
// resources/js/scbd/stack.js

export function initCardStack(gsap) {
  const cards = Array.from(document.querySelectorAll('[data-card]'));

  cards.forEach((card, index) => {
    if (index === cards.length - 1) return;

    gsap.fromTo(card,
      { scale: 1, y: 0 },
      {
        scale: 0.96 - index * 0.012,
        y: -12,
        ease: 'none',
        scrollTrigger: {
          trigger: cards[index + 1],
          start: 'top bottom',
          end: 'top 110px',
          scrub: 0.4,
        },
      });
  });
}
```

- [ ] **Step 5: Write `district.js` — the module with the important guard**

```js
// resources/js/scbd/district.js

/**
 * Pinned horizontal scroll.
 *
 * The reference computed `end: '+=' + (track.scrollWidth - innerWidth)`
 * unconditionally. With few or no district places that value is zero or
 * negative, and ScrollTrigger pins the viewport with nowhere to scroll — the
 * whole page appears frozen. This is the only failure mode that breaks the
 * entire site rather than one section, so it is guarded explicitly.
 *
 * @returns {boolean} whether pinning was created
 */
export function initDistrict(gsap, ScrollTrigger) {
  const track = document.querySelector('[data-horizontal-track]');
  const section = document.querySelector('#district');

  if (!track || !section) return false;

  const overflow = () => track.scrollWidth - window.innerWidth;

  if (overflow() <= 0) {
    gsap.set(track, { x: 0 });
    return false;
  }

  ScrollTrigger.create({
    trigger: '#district',
    start: 'top top',
    pin: true,
    scrub: 0.8,
    anticipatePin: 1,
    end: () => `+=${overflow()}`,
    onRefresh: () => gsap.set(track, { x: 0 }),
    animation: gsap.to(track, { x: () => -overflow(), ease: 'none' }),
    invalidateOnRefresh: true,
  });

  return true;
}
```

- [ ] **Step 6: Write `counters.js` and `cursor.js`**

```js
// resources/js/scbd/counters.js

export function initCounters(gsap, ScrollTrigger) {
  document.querySelectorAll('[data-count]').forEach((element) => {
    const target = parseFloat(element.dataset.to);
    if (Number.isNaN(target)) return;

    const suffix = element.dataset.suffix || '';
    const plain = element.hasAttribute('data-plain');
    const state = { value: 0 };

    const render = () => {
      const rounded = Math.round(state.value);
      element.textContent = (plain ? String(rounded) : rounded.toLocaleString()) + suffix;
    };

    ScrollTrigger.create({
      trigger: element,
      start: 'top 88%',
      once: true,
      onEnter: () =>
        gsap.to(state, { value: target, duration: 1.6, ease: 'power2.out', onUpdate: render }),
    });
  });
}
```

```js
// resources/js/scbd/cursor.js

export function initCursor(gsap) {
  const dot = document.querySelector('[data-cursor]');
  const ring = document.querySelector('[data-cursor-ring]');

  if (!dot || !ring) return;
  // Coarse pointers have no cursor to follow.
  if (window.matchMedia('(pointer: coarse)').matches) return;

  const dotX = gsap.quickTo(dot, 'x', { duration: 0.12, ease: 'power3' });
  const dotY = gsap.quickTo(dot, 'y', { duration: 0.12, ease: 'power3' });
  const ringX = gsap.quickTo(ring, 'x', { duration: 0.45, ease: 'power3' });
  const ringY = gsap.quickTo(ring, 'y', { duration: 0.45, ease: 'power3' });

  window.addEventListener('mousemove', (event) => {
    dotX(event.clientX);
    dotY(event.clientY);
    ringX(event.clientX);
    ringY(event.clientY);
  });

  document.querySelectorAll('a, button, [data-magnetic]').forEach((element) => {
    element.addEventListener('mouseenter', () =>
      gsap.to(ring, { scale: 1.9, borderColor: 'rgba(236,48,19,0.9)', duration: 0.3 }));
    element.addEventListener('mouseleave', () =>
      gsap.to(ring, { scale: 1, borderColor: 'rgba(32,30,29,0.45)', duration: 0.3 }));
  });

  document.querySelectorAll('[data-magnetic]').forEach((element) => {
    const moveX = gsap.quickTo(element, 'x', { duration: 0.4, ease: 'power3' });
    const moveY = gsap.quickTo(element, 'y', { duration: 0.4, ease: 'power3' });

    element.addEventListener('mousemove', (event) => {
      const rect = element.getBoundingClientRect();
      moveX((event.clientX - (rect.left + rect.width / 2)) * 0.35);
      moveY((event.clientY - (rect.top + rect.height / 2)) * 0.45);
    });

    element.addEventListener('mouseleave', () => {
      moveX(0);
      moveY(0);
    });
  });
}
```

- [ ] **Step 7: Write `i18n.js`**

```js
// resources/js/scbd/i18n.js
import { splitElement } from './textSplit';

/**
 * Instant, no-reload language switching. The reference used a hardcoded
 * dictionary; this reads the server-rendered payload so the copy is editable in
 * the admin. Values arrive pre-escaped with <br> line breaks.
 */
export function initLanguageSwitcher(ScrollTrigger) {
  const payloadNode = document.getElementById('scbd-i18n');
  const buttons = Array.from(document.querySelectorAll('[data-lang]'));

  if (!payloadNode || buttons.length === 0) return;

  let dictionary;
  try {
    dictionary = JSON.parse(payloadNode.textContent);
  } catch (error) {
    console.warn('SCBD: could not parse the i18n payload.', error);
    return;
  }

  const apply = (locale) => {
    const strings = dictionary[locale];
    if (!strings) return;

    document.querySelectorAll('[data-i18n]').forEach((element) => {
      const value = strings[element.dataset.i18n];
      if (value == null || value === '') return;

      element.innerHTML = value;

      if (element.hasAttribute('data-split')) {
        element._origHTML = value;
        splitElement(element);
        element.querySelectorAll('[data-char]').forEach((char) => {
          char.style.transform = 'translateY(0%)';
        });
      }
    });

    buttons.forEach((button) => {
      const active = button.dataset.lang === locale;
      button.style.background = active ? '#201e1d' : 'transparent';
      button.style.color = active ? '#f3f2f2' : '#201e1d';
    });

    document.documentElement.lang = locale;
    ScrollTrigger.refresh();
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => apply(button.dataset.lang));
  });
}
```

Re-split characters are set to `translateY(0%)` immediately rather than animated in: the user has already read the section, so replaying the entrance stagger on a language change reads as a glitch.

- [ ] **Step 8: Write `index.js`**

```js
// resources/js/scbd/index.js
import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

import { prefersReducedMotion } from './motion';
import { createSmoothScroll } from './smoothScroll';
import { splitTargets } from './textSplit';
import { runLoader } from './loader';
import { initHeader } from './header';
import { initReveals } from './reveal';
import { initMarquee } from './marquee';
import { initDistrict } from './district';
import { initCardStack } from './stack';
import { initCounters } from './counters';
import { initCursor } from './cursor';
import { initLanguageSwitcher } from './i18n';

gsap.registerPlugin(ScrollTrigger);

export function initScbd() {
  // The reference polled `window.gsap && window.ScrollTrigger && window.Lenis`
  // every 60ms because a bundled page cannot guarantee script order. With Vite
  // imports, load order is a fact — no polling, and no
  // `window.__scbdInstance` teardown singleton either.
  const reduced = prefersReducedMotion();
  const lenis = createSmoothScroll(ScrollTrigger, reduced);

  splitTargets();
  initCursor(gsap);
  initLanguageSwitcher(ScrollTrigger);

  gsap.context(() => {
    runLoader(gsap, ScrollTrigger, lenis, reduced);
    initHeader(gsap, lenis);

    if (!reduced) {
      initReveals(gsap, ScrollTrigger);
      initMarquee(gsap, ScrollTrigger);
      initDistrict(gsap, ScrollTrigger);
      initCardStack(gsap);
    } else {
      // Resting state for everything the reveals would have animated.
      gsap.set('[data-char]', { yPercent: 0 });
      gsap.set('[data-fade]', { opacity: 1, y: 0 });
      gsap.set('[data-reveal], #district img, #facilities img', {
        clipPath: 'inset(0% 0% 0% 0%)',
        scale: 1,
      });
    }

    // Counters run in both modes; the reduced path simply reaches the final
    // number faster than the eye tracks it.
    initCounters(gsap, ScrollTrigger);
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

- [ ] **Step 9: Build and commit**

```bash
npm run build
```

Expected: build succeeds with no unresolved imports. Then:

```bash
git add resources/js/scbd
git commit -m "feat: port GSAP/ScrollTrigger/Lenis animation layer to ES modules"
```

---

### Task 18: Browser verification and documentation

The animation layer has no automated coverage — there is no JS test harness in this project and adding one is out of scope. This task verifies it against a real browser and records the result honestly.

**Files:**
- Create: `docs/scbd-homepage.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: everything from Tasks 1–17.

- [ ] **Step 1: Confirm the full suite passes**

```bash
php artisan test
```

Expected: PASS. Record the actual test and assertion counts — do not claim a pass without seeing the output.

- [ ] **Step 2: Build assets and serve the app**

```bash
npm run build
php artisan storage:link
php artisan db:seed --class=Database\\Seeders\\HomepageSeeder
```

Then open `http://iat-cms.test/`. If that host is not resolving, run `php artisan serve` and use `http://127.0.0.1:8000/`.

- [ ] **Step 3: Verify each behaviour in the browser**

Check every item and note the result. Anything that fails gets written down, not smoothed over.

- [ ] Loader counts `000` → `100`, the bar fills, and the panel lifts away
- [ ] Hero characters stagger upward into place
- [ ] Hero image clip-path reveals from the bottom
- [ ] Header slides out of view on scroll-down and returns on scroll-up
- [ ] Marquee scrolls continuously and speeds up while scrolling
- [ ] `#about` content fades up on entry; the image clip-reveals
- [ ] Stats count up once — and `1987` renders as `1987`, **not** `1,987`
- [ ] `#district` pins the viewport, scrolls horizontally through every place, then releases
- [ ] Facility cards scale and stack as the next card arrives
- [ ] News rows shift right on hover
- [ ] Contact heading characters reveal from below on entry (the `yPercert` fix from Task 17)
- [ ] Cursor dot and ring follow the pointer; the ring grows and turns red over links
- [ ] `[data-magnetic]` buttons pull toward the pointer and spring back
- [ ] Anchor links scroll smoothly with a `-70px` offset
- [ ] EN / ID / CN switching replaces all text instantly, keeps scroll position, and does not break later scroll triggers
- [ ] Switching to ID then back to EN restores the original English copy

- [ ] **Step 4: Verify the two guarded failure modes**

These are the paths most likely to be wrong, and both are invisible in normal use:

```bash
# 1. District pinning must not run when the track cannot overflow.
php artisan tinker --execute="App\Models\DistrictPlace::query()->update(['is_active' => false]);"
```

Reload `/`. Expected: the page scrolls normally to the bottom, `#district` is absent, and nothing freezes. Then restore:

```bash
php artisan tinker --execute="App\Models\DistrictPlace::query()->update(['is_active' => true]);"
```

```bash
# 2. Reduced motion.
```

In the browser devtools command palette run "Emulate CSS prefers-reduced-motion: reduce", then reload. Expected: no loader sequence, all text and images already in their final state, no smooth-scroll easing, page fully usable.

- [ ] **Step 5: Verify the admin sidebar**

Open `http://iat-cms.test/superduper` and confirm:

- [ ] Five groups in order: Content, Homepage Data, Appearance, Settings, System
- [ ] Twelve items present, none duplicated
- [ ] No leftover `Blogs` group from the Story plugin
- [ ] "Admin Topbar Menu" is labelled as such, not "Topbar Menu"
- [ ] Blog Posts shows a badge when drafts or scheduled posts exist
- [ ] Homepage editor saves, and the change appears on `/` after reload
- [ ] District Places / Facilities / Stats / Public Menu all reorder by drag, and the new order shows on `/`

- [ ] **Step 6: Write the documentation**

Create `docs/scbd-homepage.md` covering: which admin screen edits which part of the homepage; how the three-locale fallback behaves; the `data-*` contract between Blade and the animation modules; how to add a nav item and mark one as the CTA; and the two guarded failure modes from Step 4 with an explanation of why each guard exists.

Add a short "SCBD homepage" section to `README.md` pointing at that file and at the spec.

- [ ] **Step 7: Commit**

```bash
git add docs/scbd-homepage.md README.md
git commit -m "docs: document the SCBD homepage content model and animation contract"
```

---

## Plan self-review

Checked against `docs/superpowers/specs/2026-07-30-scbd-homepage-cms-design.md`.

**Spec coverage.** Every spec section maps to a task:

| Spec requirement | Task |
|---|---|
| `HasTranslatableFields` with `t()`, `translations()`, en fallback | 2 |
| `homepage_contents` singleton, 14 translatable columns | 3 |
| `district_places`, `facilities` | 4 |
| `stats` with `format` (`plain`/`thousands`), `public_menu_items` | 5 |
| `site_settings` singleton | 6 |
| Images to the `public` disk; seeder fetching 9 reference images | 7, 8 |
| Seeded EN/ID/CN copy from the reference dictionary | 8 |
| Locale-as-outer-axis form layout | 9, 10 |
| `HomepageEditor`, `SiteSettingsPage`, `EditsSingletonRecord` | 9, 10, 11 |
| Four reorderable resources | 12 |
| `BlogCategoryResource` (ours), `UserResource` (new) | 13 |
| `NavigationBuilder` sidebar, five groups, plugin relabelling, pending badge | 14 |
| `HomeController` + `HomepageData` DTO + route; `welcome.blade.php` deleted | 15 |
| Blade layout and partials; no footer; `data-*` contract | 16 |
| Animation modules; four deliberate changes from the reference | 17 |
| Error handling: singleton `firstOrCreate`, per-key fallback, empty sections, district guard, missing images, loader failsafe, reduced motion | 3, 6, 15, 16, 17 |
| Automated tests | 2–17 |
| Manual browser verification | 18 |

**Corrections applied to the spec during planning.** Three items in the spec were wrong or incomplete; the plan supersedes it on each, and the spec should be amended to match:

1. **Tests are PHPUnit, not Pest.** The spec's Verification section says "Automated (Pest)". No Pest package is installed — `composer.json` has `phpunit/phpunit ^12.5.12` and the project ships `tests/TestCase.php` with `phpunit.xml`. All tests in this plan are PHPUnit.
2. **`public_menu_items` needed an `is_cta` column.** The spec assigns that table both the nav links and the header CTA but defined no discriminator. Added in Task 5.
3. **The spec lists a `footer.blade.php` partial.** The reference markup contains no `<footer>` element; the page ends at `#contact`. Dropped in Task 16.

**Decision made during planning, not in the spec.** The spec did not state how the reference's styling should be handled. The reference is bespoke CSS — 16,528 characters across three `<style>` blocks, 159 inline `style` attributes, its own `.btn` component classes, and zero Tailwind. Task 1 ports that CSS verbatim as a separate Vite entry point and self-hosts the 9 extracted Archivo WOFF2 files; Tailwind stays scoped to the admin panel. Converting the homepage to Tailwind would risk fidelity for no reuse benefit, since nothing else shares this design language yet.

**Placeholder scan.** No `TBD`, `TODO`, "implement later", or bare "add error handling" instructions. Task 16 Step 7 is transcription from a named source file at listed byte offsets against a stated binding contract, with two fully worked partials as the pattern — not a placeholder, but it is the one task whose output depends on reading the reference file rather than copying from this plan.

**Type consistency.** Verified across tasks: `t()` / `translations()` / `translatableFields()` (Task 2) used identically in 3–6, 15, 16. `singleton()` (3, 6) called from 10, 11, 15. `scopeOrdered` / `scopeActive` (4) used by 5, 15. `scopeLinks` / `scopeCta` (5) used by 15. `StatFormat::options()` (5) used by 12. `LocaleTabs::make()` / `isFallback()` (9) used by 10–13. `HomepageData::I18N_MAP` keys (15) match the `data-i18n` attributes in 16 and the lookup in `i18n.js` (17). `initScbd()` exported in Task 1's placeholder and replaced with the real implementation in 17.

**Known API risks, each with a stated fallback in the task.** `Tabs::getChildComponents()` (Task 10 Step 4), `Table::getReorderColumn()` in the test (Task 12 Step 1), `recordActions()` / `toolbarActions()` naming (Task 12 Step 8), `Resource::getRouteBaseName()` (Task 14 Step 5), and whether Story ships a `BlogPost` factory (Task 14 Step 1). These were verified to exist at the class level in Filament 5.7.4 but not exercised, so each carries an inline alternative rather than a silent assumption.
