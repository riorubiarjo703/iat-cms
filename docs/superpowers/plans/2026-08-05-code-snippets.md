# Code Snippets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `CodeSnippetsPlaceholder` with a working feature that stores operator-supplied markup and injects it into the public site's `<head>` or `<body>`.

**Architecture:** A `code_snippets` table backs an Eloquent model with two string-backed enums. `App\Support\SnippetRenderer` resolves the active snippets for one request through the existing `RequestCache`, and a single anonymous Blade component renders them at three insertion points in each of the two public layouts. The admin screen is a standard Filament 5 resource; templates are plain data that create switched-off records.

**Tech Stack:** Laravel 13, Filament 5, PHPUnit (class-based, `RefreshDatabase`, sqlite `:memory:`), Tailwind 4 via Vite.

**Spec:** `docs/superpowers/specs/2026-08-05-code-snippets-design.md`

## Global Constraints

- **Never run `php artisan migrate:fresh`** on this project. Use `php artisan migrate`. Dropping the database here has destroyed real content before.
- **Never run `npm run dev`.** Use `npm run build` when assets need rebuilding. No task in this plan requires a rebuild — no new CSS classes are introduced outside compiled Filament components.
- Tests are **PHPUnit classes**, not Pest. Namespace `Tests\Feature\…`, extend `Tests\TestCase`, `use RefreshDatabase`.
- Run tests with `php artisan test`.
- Follow `App\Enums\StatFormat` for enum shape: a `label()` method and a static `options()` returning a `value => label` map for Filament `Select::options()`.
- Follow `App\Models\SiteSetting` for cache invalidation: flush `RequestCache` from `booted()` on `saved` and `deleted`.
- Navigation placement is owned by `App\Filament\Navigation\AdminNavigation`. Resources must **not** set `$navigationGroup` or `$navigationSort`.
- Comments explain *why*, not *what*. Match the density of the surrounding code — this codebase comments decisions and non-obvious constraints, not syntax.
- The snippet code column is rendered **unescaped by design**. Any step touching output must preserve `{!! !!}`.

---

### Task 1: Snippet enums

**Files:**
- Create: `app/Enums/SnippetType.php`
- Create: `app/Enums/SnippetPosition.php`
- Test: `tests/Unit/SnippetEnumsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\SnippetType` (cases `Script`, `Style`, `Meta`, `Html`; methods `label(): string`, `icon(): string`, `static options(): array<string,string>`) and `App\Enums\SnippetPosition` (cases `Head`, `BodyStart`, `BodyEnd`; methods `label(): string`, `static options(): array<string,string>`, `static helperText(): string`). Both are string-backed with values `script|style|meta|html` and `head|body_start|body_end`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SnippetEnumsTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use PHPUnit\Framework\TestCase;

class SnippetEnumsTest extends TestCase
{
    public function test_type_options_map_values_to_labels(): void
    {
        $this->assertSame([
            'script' => 'Script',
            'style' => 'Style',
            'meta' => 'Meta',
            'html' => 'HTML',
        ], SnippetType::options());
    }

    /**
     * The list page sorts by `cases()` order rather than keeping a separate
     * sort map, so declaration order is load-bearing: it must match the order
     * the positions appear in the rendered document.
     */
    public function test_positions_are_declared_in_document_order(): void
    {
        $this->assertSame(
            ['head', 'body_start', 'body_end'],
            array_column(SnippetPosition::cases(), 'value'),
        );
    }

    public function test_position_options_map_values_to_labels(): void
    {
        $this->assertSame([
            'head' => 'Head',
            'body_start' => 'Body Start',
            'body_end' => 'Body End',
        ], SnippetPosition::options());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SnippetEnumsTest`
Expected: FAIL — `Class "App\Enums\SnippetType" not found`.

- [ ] **Step 3: Write the type enum**

Create `app/Enums/SnippetType.php`:

```php
<?php

namespace App\Enums;

/**
 * What a snippet contains. Placement is `SnippetPosition`'s job — this only
 * categorises, because the `code` column holds full tags rather than a bare
 * body, so nothing here has to wrap the operator's markup.
 */
enum SnippetType: string
{
    case Script = 'script';
    case Style = 'style';
    case Meta = 'meta';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Script => 'Script',
            self::Style => 'Style',
            self::Meta => 'Meta',
            self::Html => 'HTML',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Script => 'heroicon-o-code-bracket',
            self::Style => 'heroicon-o-paint-brush',
            self::Meta => 'heroicon-o-hashtag',
            self::Html => 'heroicon-o-document-text',
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

- [ ] **Step 4: Write the position enum**

Create `app/Enums/SnippetPosition.php`:

```php
<?php

namespace App\Enums;

/**
 * Where a snippet is injected.
 *
 * Cases are declared in document order, so `cases()` yields the order the
 * snippet list sorts by and no separate sort map has to be kept in step.
 */
enum SnippetPosition: string
{
    case Head = 'head';
    case BodyStart = 'body_start';
    case BodyEnd = 'body_end';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head',
            self::BodyStart => 'Body Start',
            self::BodyEnd => 'Body End',
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

    /** Shown under the Position field; also the gist of the Help modal. */
    public static function helperText(): string
    {
        return 'Head: analytics, meta, CSS. Body Start: tracking pixels. Body End: chat widgets.';
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SnippetEnumsTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Prove the ordering test is real**

Temporarily swap the `Head` and `BodyEnd` case declarations in `SnippetPosition`, re-run `php artisan test --filter=SnippetEnumsTest`, and confirm `test_positions_are_declared_in_document_order` **fails**. Restore the original order and confirm it passes again.

A test that would pass regardless of declaration order protects nothing, and this project has shipped exactly that kind of test before.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/SnippetType.php app/Enums/SnippetPosition.php tests/Unit/SnippetEnumsTest.php
git commit -m "feat: add snippet type and position enums"
```

---

### Task 2: Table, model and factory

**Files:**
- Create: `database/migrations/2026_08_05_100000_create_code_snippets_table.php`
- Create: `app/Models/CodeSnippet.php`
- Create: `database/factories/CodeSnippetFactory.php`
- Test: `tests/Feature/CodeSnippetModelTest.php`

**Interfaces:**
- Consumes: `App\Enums\SnippetType`, `App\Enums\SnippetPosition` from Task 1.
- Produces: `App\Models\CodeSnippet` with fillable `name, type, position, priority, code, description, is_active, skip_for_admins`; `type`/`position` cast to their enums; `is_active`/`skip_for_admins` cast to bool; `priority` cast to int; a `scopeActive(Builder $query): Builder`; and `CodeSnippet::factory()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CodeSnippetModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeSnippetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_type_and_position_to_enums(): void
    {
        $snippet = CodeSnippet::factory()->create([
            'type' => SnippetType::Style,
            'position' => SnippetPosition::BodyEnd,
        ]);

        $fresh = $snippet->fresh();

        $this->assertSame(SnippetType::Style, $fresh->type);
        $this->assertSame(SnippetPosition::BodyEnd, $fresh->position);
        $this->assertIsBool($fresh->is_active);
        $this->assertIsBool($fresh->skip_for_admins);
    }

    public function test_the_active_scope_excludes_disabled_snippets(): void
    {
        CodeSnippet::factory()->create(['name' => 'On', 'is_active' => true]);
        CodeSnippet::factory()->create(['name' => 'Off', 'is_active' => false]);

        $this->assertSame(['On'], CodeSnippet::query()->active()->pluck('name')->all());
    }

    /**
     * The renderer memoises its query for the whole request. Saving a snippet
     * without dropping that memo would show an editor a stale page and send
     * them hunting for a bug in their markup.
     */
    public function test_saving_flushes_the_request_cache(): void
    {
        RequestCache::remember('code_snippets', fn () => 'stale');

        CodeSnippet::factory()->create();

        $this->assertSame('fresh', RequestCache::remember('code_snippets', fn () => 'fresh'));
    }

    public function test_deleting_flushes_the_request_cache(): void
    {
        $snippet = CodeSnippet::factory()->create();

        RequestCache::remember('code_snippets', fn () => 'stale');

        $snippet->delete();

        $this->assertSame('fresh', RequestCache::remember('code_snippets', fn () => 'fresh'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CodeSnippetModelTest`
Expected: FAIL — `Class "App\Models\CodeSnippet" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_05_100000_create_code_snippets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_snippets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('script');
            $table->string('position')->default('head');

            // 0-100 per the design. The column permits 255; the form is the
            // constraint, so the range can be widened without a migration.
            $table->unsignedTinyInteger('priority')->default(10);

            $table->text('code');

            // Operator notes. Never rendered to the site.
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('skip_for_admins')->default(true);
            $table->timestamps();

            // The exact shape of the renderer's one query per request.
            $table->index(['is_active', 'position', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_snippets');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/CodeSnippet.php`:

```php
<?php

namespace App\Models;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Support\RequestCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Operator-supplied markup injected into the public site.
 *
 * `code` is emitted unescaped by `resources/views/components/code-snippets.blade.php`.
 * That is the feature: the trust boundary is panel access, not this column.
 */
class CodeSnippet extends Model
{
    /** @use HasFactory<\Database\Factories\CodeSnippetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'position',
        'priority',
        'code',
        'description',
        'is_active',
        'skip_for_admins',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SnippetType::class,
            'position' => SnippetPosition::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'skip_for_admins' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saved(fn () => RequestCache::flush('code_snippets'));
        static::deleted(fn () => RequestCache::flush('code_snippets'));
    }
}
```

- [ ] **Step 5: Write the factory**

Create `database/factories/CodeSnippetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CodeSnippet> */
class CodeSnippetFactory extends Factory
{
    protected $model = CodeSnippet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => SnippetType::Script,
            'position' => SnippetPosition::Head,
            'priority' => 10,
            'code' => '<script>window.snippet = true;</script>',
            'description' => null,
            'is_active' => true,

            // Off by default so a test that does not care about the admin-skip
            // rule is not silently affected by it when acting as a user.
            'skip_for_admins' => false,
        ];
    }
}
```

- [ ] **Step 6: Run the migration**

Run: `php artisan migrate`
Expected: `code_snippets` table created. **Do not run `migrate:fresh`.**

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=CodeSnippetModelTest`
Expected: PASS, 4 tests.

- [ ] **Step 8: Prove the cache-flush tests are real**

Comment out the two `static::` lines in `booted()`, re-run `php artisan test --filter=CodeSnippetModelTest`, and confirm both cache tests **fail**. Restore them and confirm they pass.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_05_100000_create_code_snippets_table.php app/Models/CodeSnippet.php database/factories/CodeSnippetFactory.php tests/Feature/CodeSnippetModelTest.php
git commit -m "feat: add code snippet model and table"
```

---

### Task 3: Snippet renderer

**Files:**
- Create: `app/Support/SnippetRenderer.php`
- Test: `tests/Feature/Support/SnippetRendererTest.php`

**Interfaces:**
- Consumes: `App\Models\CodeSnippet`, both enums, `App\Support\RequestCache`.
- Produces: `App\Support\SnippetRenderer` with `for(SnippetPosition $position): \Illuminate\Support\Collection<int, CodeSnippet>` and `shouldSkipForCurrentUser(): bool`. Resolved from the container; not a singleton (the request cache already handles memoisation).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Support/SnippetRendererTest.php`:

```php
<?php

namespace Tests\Feature\Support;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Support\SnippetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnippetRendererTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): SnippetRenderer
    {
        return app(SnippetRenderer::class);
    }

    public function test_it_returns_only_snippets_for_the_requested_position(): void
    {
        CodeSnippet::factory()->create(['name' => 'In head', 'position' => SnippetPosition::Head]);
        CodeSnippet::factory()->create(['name' => 'At body end', 'position' => SnippetPosition::BodyEnd]);

        $this->assertSame(
            ['In head'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_it_excludes_inactive_snippets(): void
    {
        CodeSnippet::factory()->create(['name' => 'On', 'is_active' => true]);
        CodeSnippet::factory()->create(['name' => 'Off', 'is_active' => false]);

        $this->assertSame(
            ['On'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_lower_priority_renders_first(): void
    {
        CodeSnippet::factory()->create(['name' => 'Last', 'priority' => 90]);
        CodeSnippet::factory()->create(['name' => 'First', 'priority' => 1]);

        $this->assertSame(
            ['First', 'Last'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    /**
     * Equal priorities fall back to insertion order rather than whatever order
     * the database happens to return, so a page's markup does not reshuffle
     * between requests.
     */
    public function test_equal_priorities_fall_back_to_creation_order(): void
    {
        CodeSnippet::factory()->create(['name' => 'One', 'priority' => 10]);
        CodeSnippet::factory()->create(['name' => 'Two', 'priority' => 10]);
        CodeSnippet::factory()->create(['name' => 'Three', 'priority' => 10]);

        $this->assertSame(
            ['One', 'Two', 'Three'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_admin_skipping_snippets_are_hidden_from_authenticated_users(): void
    {
        CodeSnippet::factory()->create(['name' => 'Tracking', 'skip_for_admins' => true]);
        CodeSnippet::factory()->create(['name' => 'Always', 'skip_for_admins' => false]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            ['Always'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_admin_skipping_snippets_render_for_guests(): void
    {
        CodeSnippet::factory()->create(['name' => 'Tracking', 'skip_for_admins' => true]);
        CodeSnippet::factory()->create(['name' => 'Always', 'skip_for_admins' => false]);

        $this->assertSame(
            ['Tracking', 'Always'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_a_position_with_no_snippets_returns_an_empty_collection(): void
    {
        $this->assertTrue($this->renderer()->for(SnippetPosition::BodyStart)->isEmpty());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SnippetRendererTest`
Expected: FAIL — `Target class [App\Support\SnippetRenderer] does not exist`.

- [ ] **Step 3: Write the renderer**

Create `app/Support/SnippetRenderer.php`:

```php
<?php

namespace App\Support;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use Illuminate\Support\Collection;

/**
 * Resolves which snippets belong at each injection point for this request.
 *
 * Both public layouts ask for three positions each, so a naive implementation
 * would run six queries per page. The whole active set is fetched once and
 * grouped instead — it is a handful of rows, and the request cache keeps it to
 * one query no matter how many positions ask.
 */
final class SnippetRenderer
{
    /** @return Collection<int, CodeSnippet> */
    public function for(SnippetPosition $position): Collection
    {
        $skipping = $this->shouldSkipForCurrentUser();

        return $this->grouped()
            ->get($position->value, collect())
            ->reject(fn (CodeSnippet $snippet) => $skipping && $snippet->skip_for_admins)
            ->values();
    }

    /**
     * Whether snippets flagged `skip_for_admins` should be withheld.
     *
     * Every account on this panel is currently a full administrator, so being
     * signed in is exactly the condition the flag describes. When roles land,
     * this is the one method that changes.
     */
    public function shouldSkipForCurrentUser(): bool
    {
        return auth()->check();
    }

    /**
     * Active snippets keyed by position value.
     *
     * The admin filter is applied after this point, not inside it — the cached
     * value must not depend on who is signed in.
     *
     * @return Collection<string, Collection<int, CodeSnippet>>
     */
    private function grouped(): Collection
    {
        return RequestCache::remember('code_snippets', fn () => CodeSnippet::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CodeSnippet $snippet) => $snippet->position->value));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SnippetRendererTest`
Expected: PASS, 7 tests.

- [ ] **Step 5: Prove the ordering and skip tests are real**

Make each of these changes one at a time, confirm the named test fails, then revert:

1. Remove `->orderBy('priority')` → `test_lower_priority_renders_first` fails.
2. Remove `->orderBy('id')` → `test_equal_priorities_fall_back_to_creation_order` may still pass on sqlite by luck; if it does, note that in the commit message rather than pretending the test is strong.
3. Remove the `->reject(...)` call → `test_admin_skipping_snippets_are_hidden_from_authenticated_users` fails.
4. Remove `->active()` → `test_it_excludes_inactive_snippets` fails.

- [ ] **Step 6: Commit**

```bash
git add app/Support/SnippetRenderer.php tests/Feature/Support/SnippetRendererTest.php
git commit -m "feat: resolve snippets per injection point"
```

---

### Task 4: Blade component and layout injection

**Files:**
- Create: `resources/views/components/code-snippets.blade.php`
- Modify: `resources/views/components/layouts/public.blade.php`
- Modify: `resources/views/components/layouts/page.blade.php`
- Test: `tests/Feature/CodeSnippetInjectionTest.php`

**Interfaces:**
- Consumes: `App\Support\SnippetRenderer`, `App\Enums\SnippetPosition`.
- Produces: `<x-code-snippets position="head|body_start|body_end" />`, usable from any Blade view.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CodeSnippetInjectionTest.php`. It seeds a homepage the same way the existing suite does — check `tests/Support/SeedsHeaderMenu.php` and `tests/Feature/BrandingTest.php` for the trait and helper names in use, and follow them:

```php
<?php

namespace Tests\Feature;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class CodeSnippetInjectionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHomepage();
    }

    public function test_a_head_snippet_renders_inside_the_head(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::Head,
            'code' => '<meta name="verify" content="abc">',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('<meta name="verify" content="abc">', $head);
    }

    public function test_a_body_end_snippet_renders_after_the_head(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::BodyEnd,
            'code' => '<script>chat()</script>',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $this->assertGreaterThan(
            strpos($html, '</head>'),
            strpos($html, '<script>chat()</script>'),
        );
    }

    /**
     * The whole feature is emitting operator markup verbatim. If someone
     * "fixes" the `{!! !!}` in the component to `{{ }}`, every snippet on the
     * site silently becomes visible text instead of running. This is the test
     * that stops that.
     */
    public function test_snippet_code_is_emitted_unescaped(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::Head,
            'code' => '<script>var x = 1 && 2;</script>',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $this->assertStringContainsString('<script>var x = 1 && 2;</script>', $html);
        $this->assertStringNotContainsString('&lt;script&gt;', $html);
    }

    public function test_inactive_snippets_do_not_reach_the_page(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>disabled()</script>',
            'is_active' => false,
        ]);

        $this->get('/')->assertSuccessful()->assertDontSee('disabled()', false);
    }

    public function test_admin_skipping_snippets_are_absent_for_signed_in_users(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>track()</script>',
            'skip_for_admins' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSuccessful()
            ->assertDontSee('track()', false);
    }

    public function test_the_admin_panel_never_renders_snippets(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>track()</script>',
            'skip_for_admins' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/superduper')
            ->assertSuccessful()
            ->assertDontSee('track()', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CodeSnippetInjectionTest`
Expected: FAIL — the snippet markup is absent from the page.

- [ ] **Step 3: Write the component**

Create `resources/views/components/code-snippets.blade.php`:

```blade
@props(['position'])

{{--
    Injection point for operator-supplied markup.

    `code` is emitted unescaped on purpose — this renders scripts, styles and
    meta tags entered in the admin, and escaping it would turn every snippet
    into visible text on the page. The trust boundary is panel access, which
    has no self-registration: accounts exist only when an administrator makes
    one. Do not "fix" this to {{ '{{ }}' }}.
--}}
@foreach (app(\App\Support\SnippetRenderer::class)->for(\App\Enums\SnippetPosition::from($position)) as $snippet)
{!! $snippet->code !!}
@endforeach
```

- [ ] **Step 4: Insert the component into `public.blade.php`**

In `resources/views/components/layouts/public.blade.php`:

- Add `<x-code-snippets position="head" />` as the **last** element before `</head>` — after the `@vite(...)` call. It goes last so a snippet cannot displace the title, meta description or the Vite tags.
- Add `<x-code-snippets position="body_start" />` immediately after the `<body>` tag.
- Add `<x-code-snippets position="body_end" />` as the last element before `</body>`, after the `scbd-i18n` script tag.

- [ ] **Step 5: Insert the component into `page.blade.php`**

Apply the same three insertions to `resources/views/components/layouts/page.blade.php`, at the equivalent points in that layout's `<head>` and `<body>`.

Both layouts declare their own `<head>`. A snippet appearing on one but not the other would be a confusing bug to chase, which is why this is a shared component and not a hand-written block in each file.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=CodeSnippetInjectionTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Verify the second layout is actually covered**

The tests above exercise whichever layout `/` uses. Confirm the other layout renders snippets too: create a published CMS page, request its slug, and assert a head snippet appears. Add that assertion as a test named `test_snippets_render_on_cms_pages_too` rather than checking by hand — the whole point of the shared component is that both layouts stay in step, and only a test keeps them there.

- [ ] **Step 8: Prove the unescaped test is real**

Change `{!! $snippet->code !!}` to `{{ $snippet->code }}` in the component, re-run, and confirm `test_snippet_code_is_emitted_unescaped` **fails**. Restore it.

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS. The layouts were modified, so existing markup tests (`ResponsiveMarkupTest`, `BrandingTest`, `FooterRenderTest`) must still be green.

- [ ] **Step 10: Commit**

```bash
git add resources/views/components/code-snippets.blade.php resources/views/components/layouts/public.blade.php resources/views/components/layouts/page.blade.php tests/Feature/CodeSnippetInjectionTest.php
git commit -m "feat: inject code snippets into the public layouts"
```

---

### Task 5: Filament resource

**Files:**
- Create: `app/Filament/Resources/CodeSnippets/CodeSnippetResource.php`
- Create: `app/Filament/Resources/CodeSnippets/Pages/ListCodeSnippets.php`
- Create: `app/Filament/Resources/CodeSnippets/Pages/CreateCodeSnippet.php`
- Create: `app/Filament/Resources/CodeSnippets/Pages/EditCodeSnippet.php`
- Modify: `app/Filament/Navigation/AdminNavigation.php`
- Delete: `app/Filament/Pages/Placeholders/CodeSnippetsPlaceholder.php`
- Test: `tests/Feature/Filament/CodeSnippetResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\CodeSnippet`, both enums.
- Produces: `App\Filament\Resources\CodeSnippets\CodeSnippetResource` with pages keyed `index`, `create`, `edit`; and `ListCodeSnippets`, which Task 6 extends with a public `applyTemplate(string $key): void` method.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/CodeSnippetResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use App\Filament\Resources\CodeSnippets\Pages\CreateCodeSnippet;
use App\Filament\Resources\CodeSnippets\Pages\EditCodeSnippet;
use App\Models\CodeSnippet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CodeSnippetResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(CodeSnippetResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_snippet(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Google Analytics',
                'type' => SnippetType::Script->value,
                'position' => SnippetPosition::Head->value,
                'priority' => 10,
                'code' => '<script>ga()</script>',
                'is_active' => true,
                'skip_for_admins' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $snippet = CodeSnippet::query()->sole();

        $this->assertSame('Google Analytics', $snippet->name);
        $this->assertSame(SnippetPosition::Head, $snippet->position);
        $this->assertTrue($snippet->skip_for_admins);
    }

    public function test_name_and_code_are_required(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm(['name' => null, 'code' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'code' => 'required']);
    }

    public function test_priority_is_capped_at_one_hundred(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Too eager',
                'code' => '<script></script>',
                'priority' => 101,
            ])
            ->call('create')
            ->assertHasFormErrors(['priority']);
    }

    public function test_priority_rejects_negative_numbers(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Backwards',
                'code' => '<script></script>',
                'priority' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['priority']);
    }

    public function test_it_edits_an_existing_snippet(): void
    {
        $snippet = CodeSnippet::factory()->create(['name' => 'Old']);

        Livewire::test(EditCodeSnippet::class, ['record' => $snippet->getKey()])
            ->fillForm(['name' => 'New'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New', $snippet->fresh()->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CodeSnippetResourceTest`
Expected: FAIL — resource class not found.

- [ ] **Step 3: Write the resource**

Create `app/Filament/Resources/CodeSnippets/CodeSnippetResource.php`:

```php
<?php

namespace App\Filament\Resources\CodeSnippets;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CodeSnippetResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = CodeSnippet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Snippet Details')
                ->description('Configure where and how this code will be injected')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Google Analytics')
                        ->helperText('A descriptive name for this snippet'),

                    Select::make('type')
                        ->options(SnippetType::options())
                        ->default(SnippetType::Script->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->helperText('Script, Style, Meta tag, or HTML'),

                    Select::make('position')
                        ->options(SnippetPosition::options())
                        ->default(SnippetPosition::Head->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->helperText(SnippetPosition::helperText()),

                    TextInput::make('priority')
                        ->numeric()
                        ->required()
                        ->default(10)
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText('Lower numbers load first (0-100)'),

                    Textarea::make('code')
                        ->required()
                        ->rows(8)
                        ->columnSpanFull()
                        ->placeholder('<script>...</script>')
                        ->extraInputAttributes(['class' => 'font-mono'])
                        ->helperText('Enter the full code including tags (e.g., <script>...</script>)'),

                    Textarea::make('description')
                        ->label('Description (Optional)')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Internal notes about this snippet...'),
                ]),

            Section::make()->schema([
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Enable this snippet immediately'),
            ]),

            Section::make()->schema([
                Toggle::make('skip_for_admins')
                    ->label("Don't load for staff/admins")
                    ->default(true)
                    ->helperText("Skip this snippet when an admin is logged in, so tracking scripts don't pollute analytics with staff sessions."),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (CodeSnippet $record): ?string => $record->description),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (SnippetType $state): string => $state->label()),

                TextColumn::make('position')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (SnippetPosition $state): string => $state->label()),

                TextColumn::make('priority')->sortable(),

                ToggleColumn::make('is_active')->label('Active'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            // Position then priority is the order snippets actually fire, which
            // is the question this list exists to answer. Newest-first would
            // tell an editor nothing useful.
            ->defaultSort(fn ($query) => $query->orderByRaw(
                "CASE position WHEN 'head' THEN 1 WHEN 'body_start' THEN 2 ELSE 3 END"
            )->orderBy('priority'))
            ->emptyStateIcon('heroicon-o-code-bracket')
            ->emptyStateHeading('No snippets yet')
            ->emptyStateDescription('Add tracking codes, analytics, or custom scripts to your site.')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCodeSnippets::route('/'),
            'create' => Pages\CreateCodeSnippet::route('/create'),
            'edit' => Pages\EditCodeSnippet::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Write the three page classes**

Create `app/Filament/Resources/CodeSnippets/Pages/ListCodeSnippets.php`:

```php
<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodeSnippets extends ListRecords
{
    protected static string $resource = CodeSnippetResource::class;

    public function getHeading(): string
    {
        return 'Code Snippets';
    }

    public function getSubheading(): ?string
    {
        return 'Inject scripts, styles, and meta tags into your pages';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Snippet'),
        ];
    }
}
```

Create `app/Filament/Resources/CodeSnippets/Pages/CreateCodeSnippet.php`:

```php
<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCodeSnippet extends CreateRecord
{
    protected static string $resource = CodeSnippetResource::class;

    public function getHeading(): string
    {
        return 'Create Code Snippet';
    }

    public function getSubheading(): ?string
    {
        return 'Add a new script, style, or meta tag to inject into pages';
    }
}
```

Create `app/Filament/Resources/CodeSnippets/Pages/EditCodeSnippet.php`:

```php
<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCodeSnippet extends EditRecord
{
    protected static string $resource = CodeSnippetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 5: Swap the placeholder in the navigation**

In `app/Filament/Navigation/AdminNavigation.php`:

- Add `use App\Filament\Resources\CodeSnippets\CodeSnippetResource;` alongside the existing `UserResource` import.
- In the System → System parent, replace
  `self::page(P\CodeSnippetsPlaceholder::class, 'Code Snippets', 'heroicon-o-code-bracket', 1),`
  with
  `self::resource(CodeSnippetResource::class, 'Code Snippets', 'heroicon-o-code-bracket', 1),`

Then delete `app/Filament/Pages/Placeholders/CodeSnippetsPlaceholder.php`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=CodeSnippetResourceTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Run the navigation tests**

Run: `php artisan test --filter="AdminNavigationTest|OrderedResourcesTest|AdminShellStructureTest"`
Expected: PASS. These assert the sidebar's shape and will catch a broken swap. If one asserts a placeholder count or a specific class list, update it to expect the resource — do not weaken the assertion to make it pass.

- [ ] **Step 8: Prove the priority-range tests are real**

Remove `->maxValue(100)` from the priority field, re-run `php artisan test --filter=CodeSnippetResourceTest`, and confirm `test_priority_is_capped_at_one_hundred` **fails**. Restore it. Repeat for `->minValue(0)` and the negative-number test.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/CodeSnippets app/Filament/Navigation/AdminNavigation.php tests/Feature/Filament/CodeSnippetResourceTest.php
git rm app/Filament/Pages/Placeholders/CodeSnippetsPlaceholder.php
git commit -m "feat: add the code snippets admin resource"
```

---

### Task 6: Templates and help

**Files:**
- Create: `app/Support/SnippetTemplates.php`
- Create: `resources/views/filament/modals/snippet-templates.blade.php`
- Modify: `app/Filament/Resources/CodeSnippets/Pages/ListCodeSnippets.php`
- Modify: `app/Filament/Resources/CodeSnippets/CodeSnippetResource.php` (empty-state actions)
- Test: `tests/Feature/Filament/SnippetTemplatesTest.php`

**Interfaces:**
- Consumes: `App\Models\CodeSnippet`, both enums, `ListCodeSnippets` from Task 5.
- Produces: `App\Support\SnippetTemplates::all(): array<string, array{label:string,description:string,icon:string,snippets:array<int,array<string,mixed>>}>` and `::find(string $key): ?array`; plus `ListCodeSnippets::applyTemplate(string $key): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/SnippetTemplatesTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enums\SnippetPosition;
use App\Filament\Resources\CodeSnippets\Pages\ListCodeSnippets;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Support\SnippetTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SnippetTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_all_six_templates_are_defined(): void
    {
        $this->assertSame(
            ['gtm', 'ga4', 'meta_pixel', 'crisp', 'custom_css', 'custom_js'],
            array_keys(SnippetTemplates::all()),
        );
    }

    /**
     * Every tracking template ships with a placeholder id. If templates created
     * active snippets, one click would inject a broken tag into every page of
     * the live site before the operator could type their real id.
     */
    public function test_a_template_creates_its_snippet_switched_off(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'ga4');

        $snippet = CodeSnippet::query()->sole();

        $this->assertFalse($snippet->is_active);
        $this->assertSame(SnippetPosition::Head, $snippet->position);
    }

    public function test_google_tag_manager_creates_two_snippets_in_two_positions(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'gtm');

        $snippets = CodeSnippet::query()->orderBy('id')->get();

        $this->assertCount(2, $snippets);
        $this->assertSame(SnippetPosition::Head, $snippets[0]->position);
        $this->assertSame(SnippetPosition::BodyStart, $snippets[1]->position);
        $this->assertFalse($snippets[0]->is_active);
        $this->assertFalse($snippets[1]->is_active);
    }

    public function test_an_unknown_template_key_creates_nothing(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'nope');

        $this->assertSame(0, CodeSnippet::query()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SnippetTemplatesTest`
Expected: FAIL — `Class "App\Support\SnippetTemplates" not found`.

- [ ] **Step 3: Write the template definitions**

Create `app/Support/SnippetTemplates.php`. Templates are data, so adding a seventh is an array entry rather than a new class:

```php
<?php

namespace App\Support;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;

/**
 * Starting points for the common tracking integrations.
 *
 * Each ships with a placeholder id rather than a real one, which is why
 * applying a template creates switched-off records — see
 * ListCodeSnippets::applyTemplate().
 *
 * @phpstan-type Template array{label: string, description: string, icon: string, snippets: array<int, array<string, mixed>>}
 */
final class SnippetTemplates
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'gtm' => [
                'label' => 'Google Tag Manager',
                'description' => 'Manage all your tags in one place. Creates two snippets (head + body start).',
                'icon' => 'heroicon-o-tag',
                'snippets' => [
                    [
                        'name' => 'Google Tag Manager (head)',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 1,
                        'code' => <<<'HTML'
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
<!-- End Google Tag Manager -->
HTML,
                    ],
                    [
                        'name' => 'Google Tag Manager (body)',
                        'type' => SnippetType::Html,
                        'position' => SnippetPosition::BodyStart,
                        'priority' => 1,
                        'code' => <<<'HTML'
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML,
                    ],
                ],
            ],

            'ga4' => [
                'label' => 'Google Analytics 4',
                'description' => 'Use if you need custom GA4 configuration.',
                'icon' => 'heroicon-o-chart-bar',
                'snippets' => [
                    [
                        'name' => 'Google Analytics 4',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 5,
                        'code' => <<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
HTML,
                    ],
                ],
            ],

            'meta_pixel' => [
                'label' => 'Meta / Facebook Pixel',
                'description' => 'Track conversions and build audiences for Meta ads.',
                'icon' => 'heroicon-o-share',
                'snippets' => [
                    [
                        'name' => 'Meta Pixel',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::Head,
                        'priority' => 5,
                        'code' => <<<'HTML'
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', 'YOUR_PIXEL_ID');
fbq('track', 'PageView');
</script>
<!-- End Meta Pixel Code -->
HTML,
                    ],
                ],
            ],

            'crisp' => [
                'label' => 'Crisp Chat',
                'description' => 'Add live chat widget to your website.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'snippets' => [
                    [
                        'name' => 'Crisp Chat',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::BodyEnd,
                        'priority' => 50,
                        'code' => <<<'HTML'
<script type="text/javascript">
  window.$crisp=[];
  window.CRISP_WEBSITE_ID="YOUR_WEBSITE_ID";
  (function(){
    var d=document,s=d.createElement("script");
    s.src="https://client.crisp.chat/l.js";
    s.async=1;
    d.getElementsByTagName("head")[0].appendChild(s);
  })();
</script>
HTML,
                    ],
                ],
            ],

            'custom_css' => [
                'label' => 'Custom CSS',
                'description' => 'Add custom styles to your site.',
                'icon' => 'heroicon-o-paint-brush',
                'snippets' => [
                    [
                        'name' => 'Custom CSS',
                        'type' => SnippetType::Style,
                        'position' => SnippetPosition::Head,
                        'priority' => 90,
                        'code' => "<style>\n/* Your custom styles */\n</style>",
                    ],
                ],
            ],

            'custom_js' => [
                'label' => 'Custom JavaScript',
                'description' => 'Add custom JavaScript to your site.',
                'icon' => 'heroicon-o-code-bracket',
                'snippets' => [
                    [
                        'name' => 'Custom JavaScript',
                        'type' => SnippetType::Script,
                        'position' => SnippetPosition::BodyEnd,
                        'priority' => 90,
                        'code' => "<script>\n// Your custom script\n</script>",
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
```

- [ ] **Step 4: Add `applyTemplate` to the list page**

Modify `app/Filament/Resources/CodeSnippets/Pages/ListCodeSnippets.php` — add these imports and members, keeping everything from Task 5:

```php
use App\Models\CodeSnippet;
use App\Support\SnippetTemplates;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
```

Add to `getHeaderActions()`, **before** the existing `CreateAction`, so the order matches the design (Help, Template, Add Snippet):

```php
Action::make('help')
    ->label('Help')
    ->icon('heroicon-o-question-mark-circle')
    ->color('gray')
    ->modalHeading('About code snippets')
    ->modalDescription(SnippetPosition::helperText().' Within a position, lower priority numbers load first.')
    ->modalSubmitAction(false)
    ->modalCancelActionLabel('Close'),

Action::make('template')
    ->label('Template')
    ->icon('heroicon-o-squares-2x2')
    ->color('gray')
    ->modalHeading('Use Template')
    ->modalDescription('Choose a template to quickly add tracking codes or custom snippets.')
    ->modalContent(view('filament.modals.snippet-templates', [
        'templates' => SnippetTemplates::all(),
    ]))
    ->modalSubmitAction(false)
    ->modalCancelAction(false),
```

Add the `use App\Enums\SnippetPosition;` import for the help copy.

Then add the public method:

```php
/**
 * Creates a template's snippets, switched off, and opens the first for editing.
 *
 * Off, because every tracking template carries a placeholder id — activating
 * on creation would put a broken tag on every page of the live site before the
 * operator had a chance to type their own. It also handles Google Tag Manager,
 * which needs two records in two positions and so does not fit pre-filling a
 * single create form.
 */
public function applyTemplate(string $key): void
{
    $template = SnippetTemplates::find($key);

    if ($template === null) {
        return;
    }

    $created = collect($template['snippets'])->map(fn (array $attributes) => CodeSnippet::create([
        ...$attributes,
        'is_active' => false,
        'skip_for_admins' => true,
        'description' => 'Created from the '.$template['label'].' template. Replace the placeholder id, then switch it on.',
    ]));

    Notification::make()
        ->title(count($created) === 1
            ? $created->first()->name.' created, switched off'
            : count($created).' snippets created, switched off')
        ->body('Replace the placeholder id, then enable it.')
        ->success()
        ->send();

    $this->redirect(CodeSnippetResource::getUrl('edit', ['record' => $created->first()]));
}
```

- [ ] **Step 5: Write the modal view**

Create `resources/views/filament/modals/snippet-templates.blade.php`. The modal renders inside the `ListCodeSnippets` Livewire component, so `wire:click` reaches `applyTemplate` directly:

```blade
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    @foreach ($templates as $key => $template)
        <button
            type="button"
            wire:click="applyTemplate('{{ $key }}')"
            class="fi-btn-template flex flex-col items-start gap-2 rounded-xl border border-gray-200 p-4 text-left transition hover:border-primary-500 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
        >
            <span class="flex size-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/10">
                <x-filament::icon :icon="$template['icon']" class="size-5 text-gray-500 dark:text-gray-400" />
            </span>
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $template['label'] }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $template['description'] }}</span>
        </button>
    @endforeach
</div>
```

- [ ] **Step 6: Add the empty-state actions**

In `CodeSnippetResource::table()`, add below `emptyStateDescription(...)`:

```php
->emptyStateActions([
    \Filament\Actions\CreateAction::make()->label('Add Snippet'),
])
```

The empty state's "Use Template" button is intentionally omitted here: `emptyStateActions` are table actions and cannot reach the page's `applyTemplate` method the way the header modal does. The Template button in the header is present on the empty page too, so the path is never unavailable — it is one button, not two. If you want both, that is a follow-up, not a silent workaround.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=SnippetTemplatesTest`
Expected: PASS, 4 tests.

- [ ] **Step 8: Prove the inactive-creation test is real**

Change `'is_active' => false` to `true` in `applyTemplate`, re-run, and confirm both `test_a_template_creates_its_snippet_switched_off` and `test_google_tag_manager_creates_two_snippets_in_two_positions` **fail**. Restore.

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS, everything green.

- [ ] **Step 10: Verify in the browser**

Sign in to `http://iat-cms.test/superduper` and open System → Code Snippets. Confirm, by looking:

1. The empty state shows the icon, "No snippets yet" and the Add Snippet button.
2. Help, Template and Add Snippet appear in the header, in that order.
3. Template opens the modal with all six cards in a two-column grid.
4. Clicking Google Tag Manager lands you on an edit form for a switched-off snippet, and the list then shows two GTM records.
5. Create a snippet with `<script>console.log('snippet ok')</script>` at Body End, `skip_for_admins` off, Active on — then load the public site in a **logged-out** window and confirm the console message appears.
6. Switch `skip_for_admins` on, reload the public site while signed in, and confirm it no longer fires.

Step 5 and 6 are the ones that prove the feature works end to end. A green suite is not the same as markup reaching a real page.

- [ ] **Step 11: Commit**

```bash
git add app/Support/SnippetTemplates.php resources/views/filament/modals/snippet-templates.blade.php app/Filament/Resources/CodeSnippets tests/Feature/Filament/SnippetTemplatesTest.php
git commit -m "feat: add code snippet templates"
```

---

## Self-review notes

Checked against `docs/superpowers/specs/2026-08-05-code-snippets-design.md`:

| Spec section | Task |
|---|---|
| Data model, enums, model | 1, 2 |
| Rendering / `SnippetRenderer` | 3 |
| `<x-code-snippets>`, both layouts, insertion points | 4 |
| Admin list, form, navigation swap | 5 |
| Templates, inactive creation, Help action | 6 |
| Security posture (unescaped output documented + tested) | 2 (model docblock), 4 (component comment + test) |
| Testing section | spread across 1–6, plus browser verification in 6 |

Two spec details that shifted during planning, both noted at the point they occur:

- The **empty state has one action, not two** (Task 6 Step 6). `emptyStateActions` are table-level and cannot call the page method the Template modal needs. The header's Template button covers the empty page, so nothing is unreachable.
- **Sorting by position** uses an explicit `CASE` expression (Task 5 Step 3) rather than enum `cases()` order, because the sort happens in SQL. The enum's declaration order is still the source of truth for the intended order, and Task 1 tests it.
