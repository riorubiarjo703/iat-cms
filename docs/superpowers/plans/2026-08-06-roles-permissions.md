# Roles and Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `RolesPlaceholder` and `PermissionsPlaceholder` with two working screens backed by a real authorization system that actually restricts what a user can reach.

**Architecture:** `spatie/laravel-permission` provides the tables and the `HasRoles` trait. Two app models extend Spatie's so policy auto-discovery and two extra columns (`description`, `is_system`) work. A permission catalogue derived from `AdminNavigation` is seeded into two roles. Enforcement is three layers — the panel gate, resource policies, and explicit navigation filtering — because the `NavigationBuilder` bypasses Filament's own policy-based hiding.

**Tech Stack:** Laravel 13.23, Filament 5, spatie/laravel-permission 8.3, PHPUnit (class-based, `RefreshDatabase`, sqlite `:memory:`).

**Spec:** `docs/superpowers/specs/2026-08-06-roles-permissions-design.md`

## Global Constraints

- **Never run `php artisan migrate:fresh`.** Use `php artisan migrate`. Dropping this database has destroyed real content before.
- **Never run `npm run dev`.** `npm run build` if assets change — no task here introduces CSS classes outside compiled Filament components, so no rebuild is expected.
- **Test runs take 1.5-3 minutes of wall clock each, even filtered.** That is normal. Never run two test commands concurrently — it deadlocks the php container and leaves orphaned processes that poison later runs. Run focused filters sequentially. Do not run the full suite; the controller does that.
- Tests are **PHPUnit classes**, not Pest. Namespace `Tests\Feature\…`, extend `Tests\TestCase`, `use RefreshDatabase`.
- Run tests with `php artisan test --filter=…`.
- Navigation placement is owned by `App\Filament\Navigation\AdminNavigation`. Resources must **not** set `$navigationGroup` or `$navigationSort`.
- Follow `app/Filament/Resources/CodeSnippets/CodeSnippetResource.php` for Filament 5 resource conventions and `tests/Feature/Filament/CodeSnippetResourceTest.php` for resource test conventions.
- Comments explain *why*, not *what*, matching the surrounding density. This codebase comments decisions and constraints, never syntax.
- **Permission names are exact strings.** They appear in seeders, policies, navigation and tests. A typo produces a permission that gates nothing and a screen nobody can reach. Copy them verbatim from Task 2's catalogue.

## The permission catalogue (referenced by several tasks)

Defined once in Task 2 as `App\Support\PermissionCatalogue`. Reproduced here so later tasks can be read independently:

```
dashboard.view
posts.view  posts.create  posts.update  posts.delete
pages.view  pages.create  pages.update  pages.delete
categories.view  categories.create  categories.update  categories.delete
users.view  users.create  users.update  users.delete
code-snippets.view  code-snippets.create  code-snippets.update  code-snippets.delete
contacts.view  contacts.update  contacts.delete
media.manage  menus.manage  settings.manage  roles.manage  permissions.manage
admin.access
content-blocks.view  comments.view  newsletter.view  announcements.view
advertisements.view  ad-zones.view  social-posting.view  analytics.view
email-activity.view  redirects.view  backups.view  template-pages.view
template-settings.view  translations.view  theme-editor.view
```

45 permissions. `admin.access` is the only `is_system` one.

`content_editor` holds exactly these 16:

```
dashboard.view
posts.view  posts.create  posts.update  posts.delete
pages.view  pages.update
categories.view  categories.create  categories.update  categories.delete
content-blocks.view  comments.view
media.manage  menus.manage
admin.access
```

---

### Task 1: Install Spatie, extend the models, add the columns

**Files:**
- Modify: `composer.json` (via composer)
- Create: `config/permission.php` (published)
- Create: `database/migrations/<ts>_create_permission_tables.php` (published)
- Create: `database/migrations/<ts>_add_description_and_is_system_to_permission_tables.php`
- Create: `app/Models/Role.php`
- Create: `app/Models/Permission.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/RolesAndPermissionsModelTest.php`

**Interfaces:**
- Produces: `App\Models\Role` and `App\Models\Permission` (each extending Spatie's, adding an `is_system` boolean cast; `Role` also has a nullable `description`), registered in `config/permission.php` so Spatie and Laravel's policy auto-discovery both resolve them. `App\Models\User` gains Spatie's `HasRoles` trait — giving later tasks `assignRole()`, `hasRole()`, `givePermissionTo()` and `can()`.

- [ ] **Step 1: Install the package and publish its files**

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

This publishes `config/permission.php` and the `create_permission_tables` migration. Do not run `migrate` yet — Step 3 adds a second migration that should land in the same pass.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/RolesAndPermissionsModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_role_model_is_the_one_spatie_resolves(): void
    {
        $role = Role::create(['name' => 'editor']);

        $this->assertInstanceOf(Role::class, Role::findByName('editor'));
        $this->assertInstanceOf(Role::class, $role);
    }

    public function test_roles_carry_a_description_and_a_system_flag(): void
    {
        $role = Role::create([
            'name' => 'super_admin',
            'description' => 'Full access',
            'is_system' => true,
        ]);

        $fresh = $role->fresh();

        $this->assertSame('Full access', $fresh->description);
        $this->assertTrue($fresh->is_system);
    }

    /**
     * The flag must default to false. A protected-by-default row would make
     * every role created from the admin screen undeletable.
     */
    public function test_the_system_flag_defaults_to_false(): void
    {
        $this->assertFalse(Role::create(['name' => 'editor'])->fresh()->is_system);
        $this->assertFalse(Permission::create(['name' => 'posts.view'])->fresh()->is_system);
    }

    public function test_users_can_hold_roles_and_inherit_their_permissions(): void
    {
        $permission = Permission::create(['name' => 'posts.view']);
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue($user->fresh()->can('posts.view'));
        $this->assertFalse($user->fresh()->can('posts.delete'));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=RolesAndPermissionsModelTest`
Expected: FAIL — `Class "App\Models\Role" not found`.

- [ ] **Step 4: Write the column migration**

Create `database/migrations/<timestamp>_add_description_and_is_system_to_permission_tables.php`, using a timestamp **after** the published `create_permission_tables` migration so it runs second:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');

            // A column rather than a name check: protecting super_admin by
            // comparing its name would stop protecting it the moment somebody
            // renamed it, and renaming is allowed.
            $table->boolean('is_system')->default(false);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('is_system')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
```

- [ ] **Step 5: Write the two models**

Create `app/Models/Role.php`:

```php
<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's role, extended so this application owns it.
 *
 * Two reasons the subclass exists: the extra `description` and `is_system`
 * columns, and Laravel's policy auto-discovery, which looks for
 * `App\Policies\RolePolicy` beside `App\Models\Role` and would never find a
 * policy for a model living in the vendor namespace.
 */
class Role extends SpatieRole
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
```

Create `app/Models/Permission.php`:

```php
<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie's permission, extended for the same two reasons as App\Models\Role:
 * the `is_system` column, and policy auto-discovery.
 */
class Permission extends SpatiePermission
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
```

- [ ] **Step 6: Point Spatie's config at them**

In `config/permission.php`, under `'models'`, replace the two class references:

```php
'models' => [
    'permission' => App\Models\Permission::class,
    'role' => App\Models\Role::class,
],
```

- [ ] **Step 7: Add `HasRoles` to the User model**

In `app/Models/User.php`, import `Spatie\Permission\Traits\HasRoles` and add it to the `use` statement inside the class, alongside `HasFactory, Notifiable`.

Leave `canAccessPanel()` alone — Task 3 owns it. Changing it here would lock every account out before the seeder that grants roles exists.

- [ ] **Step 8: Run the migrations**

Run: `php artisan migrate`
Expected: both permission migrations run. **Never `migrate:fresh`.**

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=RolesAndPermissionsModelTest`
Expected: PASS, 4 tests.

- [ ] **Step 10: Prove the model-swap test is real**

Revert `config/permission.php`'s `'role'` entry to `Spatie\Permission\Models\Role::class`, re-run, and confirm `test_the_app_role_model_is_the_one_spatie_resolves` **fails**. Restore it.

That test is the one thing standing between you and a silently unenforceable `RolePolicy` later.

- [ ] **Step 11: Commit**

```bash
git add composer.json composer.lock config/permission.php database/migrations app/Models/Role.php app/Models/Permission.php app/Models/User.php tests/Feature/RolesAndPermissionsModelTest.php
git commit -m "feat: add roles and permissions tables"
```

---

### Task 2: The permission catalogue and seeder

**Files:**
- Create: `app/Support/PermissionCatalogue.php`
- Create: `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Seeders/RolesAndPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Role`, `App\Models\Permission` from Task 1.
- Produces: `App\Support\PermissionCatalogue` with `static all(): array<int, string>`, `static systemPermissions(): array<int, string>`, `static contentEditorPermissions(): array<int, string>`, and `static groupLabel(string $permission): string` (the label the Roles modal groups checkboxes under). Also `RolesAndPermissionsSeeder`, which later tasks' tests call via `$this->seed(RolesAndPermissionsSeeder::class)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/RolesAndPermissionsSeederTest.php`:

```php
<?php

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_every_catalogued_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            count(PermissionCatalogue::all()),
            Permission::query()->count(),
        );

        foreach (PermissionCatalogue::all() as $name) {
            $this->assertTrue(
                Permission::query()->where('name', $name)->exists(),
                "The \"{$name}\" permission should exist."
            );
        }
    }

    public function test_super_admin_holds_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $role = Role::findByName('super_admin');

        $this->assertTrue($role->is_system);
        $this->assertSame(
            count(PermissionCatalogue::all()),
            $role->permissions()->count(),
        );
    }

    public function test_content_editor_holds_exactly_its_catalogued_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $role = Role::findByName('content_editor');
        $held = $role->permissions()->pluck('name')->sort()->values()->all();
        $expected = collect(PermissionCatalogue::contentEditorPermissions())->sort()->values()->all();

        $this->assertSame($expected, $held);
        $this->assertFalse($role->is_system, 'content_editor must stay deletable.');
    }

    /**
     * The site's structure is the administrator's. An editor fills pages in;
     * it does not add or remove them.
     */
    public function test_content_editor_can_neither_create_nor_delete_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $held = Role::findByName('content_editor')->permissions()->pluck('name');

        $this->assertContains('pages.update', $held);
        $this->assertNotContains('pages.create', $held);
        $this->assertNotContains('pages.delete', $held);
    }

    public function test_admin_access_is_the_only_system_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            ['admin.access'],
            Permission::query()->where('is_system', true)->pluck('name')->all(),
        );
    }

    /** Re-seeding is routine while roles are being tuned. */
    public function test_seeding_twice_neither_duplicates_nor_strips(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(PermissionCatalogue::all()), Permission::query()->count());
        $this->assertSame(2, Role::query()->count());
        $this->assertSame(
            count(PermissionCatalogue::all()),
            Role::findByName('super_admin')->permissions()->count(),
        );
    }

    /**
     * A permission added after seeding — by a new feature, or by hand — must
     * reach super_admin on the next run. Otherwise the top role silently lacks
     * an ability nobody thinks to check.
     */
    public function test_re_seeding_grants_super_admin_a_permission_added_later(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Permission::create(['name' => 'invented.later']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('super_admin')->hasPermissionTo('invented.later'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RolesAndPermissionsSeederTest`
Expected: FAIL — `Class "App\Support\PermissionCatalogue" not found`.

- [ ] **Step 3: Write the catalogue**

Create `app/Support/PermissionCatalogue.php`:

```php
<?php

namespace App\Support;

/**
 * Every permission this panel recognises, and which role holds which.
 *
 * Derived from App\Filament\Navigation\AdminNavigation's destinations rather
 * than copied from the reference product, so each sidebar entry has something
 * that gates it. Fifteen of these gate a screen that currently says "not built
 * yet" — the sidebar deliberately shows the product's full intended shape, and
 * a list covering only built features would need editing every time a
 * placeholder became real.
 *
 * Names are exact strings shared by the seeder, the policies, the navigation
 * and the tests. A typo produces a permission that gates nothing and a screen
 * nobody can reach.
 */
final class PermissionCatalogue
{
    /** Built features with real CRUD verbs. */
    private const CRUD = [
        'posts' => ['view', 'create', 'update', 'delete'],
        'pages' => ['view', 'create', 'update', 'delete'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'delete'],
        'code-snippets' => ['view', 'create', 'update', 'delete'],

        // No create: messages arrive from the public contact form, never
        // from the panel.
        'contacts' => ['view', 'update', 'delete'],
    ];

    /** Built single-screen destinations. */
    private const SINGLE = [
        'dashboard.view',
        'media.manage',
        'menus.manage',
        'settings.manage',
        'roles.manage',
        'permissions.manage',
    ];

    /** The panel gate. Deleting it would lock out everyone, permanently. */
    private const GATE = 'admin.access';

    /** Placeholder screens: view only, because there is nothing to edit yet. */
    private const PLACEHOLDERS = [
        'content-blocks.view',
        'comments.view',
        'newsletter.view',
        'announcements.view',
        'advertisements.view',
        'ad-zones.view',
        'social-posting.view',
        'analytics.view',
        'email-activity.view',
        'redirects.view',
        'backups.view',
        'template-pages.view',
        'template-settings.view',
        'translations.view',
        'theme-editor.view',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        $crud = [];

        foreach (self::CRUD as $feature => $verbs) {
            foreach ($verbs as $verb) {
                $crud[] = "{$feature}.{$verb}";
            }
        }

        return [...$crud, ...self::SINGLE, self::GATE, ...self::PLACEHOLDERS];
    }

    /** @return array<int, string> */
    public static function systemPermissions(): array
    {
        return [self::GATE];
    }

    /**
     * The content editor: the Content menu, Navigation Menus, the dashboard.
     *
     * Pages are view and update only. Everything else in Content is full CRUD,
     * because an editor who cannot publish a post is not a content editor.
     *
     * @return array<int, string>
     */
    public static function contentEditorPermissions(): array
    {
        return [
            'dashboard.view',
            'posts.view', 'posts.create', 'posts.update', 'posts.delete',
            'pages.view', 'pages.update',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'content-blocks.view',
            'comments.view',
            'media.manage',
            'menus.manage',
            self::GATE,
        ];
    }

    /**
     * The heading a permission sits under in the Roles modal. A flat list of
     * forty-five checkboxes is unusable.
     */
    public static function groupLabel(string $permission): string
    {
        $feature = str_contains($permission, '.')
            ? strstr($permission, '.', true)
            : $permission;

        return str($feature)->replace('-', ' ')->title()->toString();
    }
}
```

- [ ] **Step 4: Write the seeder**

Create `database/seeders/RolesAndPermissionsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every catalogued permission and the two roles.
 *
 * Idempotent in both directions: re-running neither duplicates rows nor strips
 * assignments. super_admin is re-synced to hold everything on every run, which
 * is how a permission added by a later feature reaches it — there is no
 * wildcard, deliberately, because a Gate::before bypass would make the Roles
 * screen show a permission count with no relationship to what the role can do.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie caches the permission map. Without this the roles created
        // below cannot see permissions created moments earlier in the same
        // process, which is the classic silent-empty-role bug.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $system = PermissionCatalogue::systemPermissions();

        foreach (PermissionCatalogue::all() as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['is_system' => in_array($name, $system, true)],
            );
        }

        $superAdmin = Role::query()->updateOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['description' => 'Full access to every screen and setting.', 'is_system' => true],
        );

        // syncPermissions rather than givePermissionTo: it is what makes a
        // permission introduced after the last run reach this role.
        $superAdmin->syncPermissions(Permission::query()->pluck('name')->all());

        $editor = Role::query()->updateOrCreate(
            ['name' => 'content_editor', 'guard_name' => 'web'],
            ['description' => 'Content, media and navigation. Cannot add or remove pages.', 'is_system' => false],
        );

        $editor->syncPermissions(PermissionCatalogue::contentEditorPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

- [ ] **Step 5: Join the DatabaseSeeder chain**

In `database/seeders/DatabaseSeeder.php`, add `RolesAndPermissionsSeeder::class` as the **first** entry of the `$this->call([...])` array, before `HomepageSeeder::class`. Roles must exist before anything assigns one.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=RolesAndPermissionsSeederTest`
Expected: PASS, 7 tests.

- [ ] **Step 7: Confirm the existing seeder suite still passes**

Run: `php artisan test --filter=DatabaseSeederTest`
Expected: PASS, 6 tests. The chain gained an entry; nothing else should change.

- [ ] **Step 8: Prove the re-sync test is real**

Change `syncPermissions(...)` on `$superAdmin` to `givePermissionTo(...)` of only `PermissionCatalogue::all()`, re-run, and confirm `test_re_seeding_grants_super_admin_a_permission_added_later` **fails** (the later-added permission is not in the catalogue, so it is never granted). Restore `syncPermissions`.

This is the exact bug the no-wildcard decision creates, so the test guarding it must bite.

- [ ] **Step 9: Commit**

```bash
git add app/Support/PermissionCatalogue.php database/seeders/RolesAndPermissionsSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Seeders/RolesAndPermissionsSeederTest.php
git commit -m "feat: seed the permission catalogue and two roles"
```

---

### Task 3: The panel gate and the lockout migration

**Files:**
- Modify: `app/Models/User.php`
- Create: `database/migrations/<ts>_grant_super_admin_to_existing_users.php`
- Test: `tests/Feature/PanelAccessTest.php`

**Interfaces:**
- Consumes: `HasRoles` from Task 1, `RolesAndPermissionsSeeder` from Task 2.
- Produces: `User::canAccessPanel()` returning `$this->can('admin.access')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PanelAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function panel(): \Filament\Panel
    {
        return Filament::getPanel('admin');
    }

    /**
     * The lockout migration grants super_admin to everyone who existed before
     * this shipped, which means every user in a normal test also has access.
     * That would make a gate stuck at `true` indistinguishable from a working
     * one. This asserts the refusal directly.
     */
    public function test_a_user_with_no_roles_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_a_super_admin_can_access_the_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue($user->fresh()->canAccessPanel($this->panel()));
    }

    public function test_a_content_editor_can_access_the_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('content_editor');

        $this->assertTrue($user->fresh()->canAccessPanel($this->panel()));
    }

    public function test_a_roleless_user_is_bounced_from_the_panel_over_http(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/superduper')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PanelAccessTest`
Expected: FAIL — `canAccessPanel` still returns `true`, so the refusal assertions fail.

- [ ] **Step 3: Gate the panel**

In `app/Models/User.php`, replace `canAccessPanel()`'s body and docblock:

```php
    /**
     * Panel access is a permission, not merely authentication.
     *
     * This returned `true` for every authenticated account until roles
     * existed. The migration that shipped alongside this granted super_admin
     * to everyone who already had a login, so nobody's access changed on
     * deploy — demotion is a deliberate act on the Users screen.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('admin.access');
    }
```

- [ ] **Step 4: Write the lockout migration**

Create `database/migrations/<timestamp>_grant_super_admin_to_existing_users.php`:

```php
<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * The operator accounts predating roles would otherwise be locked out the
 * moment canAccessPanel() started checking a permission. They get super_admin,
 * which is exactly the access they already had, and can be demoted from the
 * Users screen afterwards.
 *
 * Named accounts rather than "every user", deliberately. The installation also
 * carries test@example.com — the local convenience login whose password is
 * published in DatabaseSeeder — and two factory accounts. Granting super_admin
 * to a known credential would hand full administrative access to anyone who
 * has read the seeder, which is the opposite of what this feature is for.
 * Those three keep their logins and lose panel access, which is the correct
 * outcome for a throwaway.
 *
 * A fresh install matches none of these and correctly does nothing.
 */
return new class extends Migration
{
    /** The real operator accounts on this installation. */
    private const OPERATORS = [
        'admin@iat-cms.test',
        'rio.rubiarjo@iat.id',
        'klein.burnice@example.org',
    ];

    public function up(): void
    {
        $role = Role::query()->where('name', 'super_admin')->first();

        // RolesAndPermissionsSeeder has not run yet — on a fresh install there
        // is nobody to rescue either, so there is nothing to do.
        if ($role === null) {
            return;
        }

        User::query()
            ->whereIn('email', self::OPERATORS)
            ->each(function (User $user) use ($role): void {
                if ($user->roles()->count() === 0) {
                    $user->assignRole($role);
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: which users held super_admin before this ran
        // is not recorded, and guessing would either strip access from someone
        // who should keep it or leave it with someone who should not.
    }
};
```

- [ ] **Step 5: Run the migration**

Run: `php artisan migrate`
Expected: the grant migration runs. **Never `migrate:fresh`.**

Then confirm by hand that your own account still works — this is the step that could lock you out:

```bash
php artisan tinker --execute="\App\Models\User::query()->get()->each(fn(\$u) => print(\$u->email.' => '.\$u->getRoleNames()->implode(',').PHP_EOL));"
```

The three accounts in `OPERATORS` must print `super_admin`. `test@example.com`,
`ereichel@example.org` and `erempel@example.net` must print empty — they keep
their logins and lose panel access, deliberately.

If any of the three operator accounts prints empty, **stop and report BLOCKED**
rather than continuing. The tests run against in-memory sqlite and can tell you
nothing about the live database.

**Prerequisite, already done by the controller:** `RolesAndPermissionsSeeder`
has been run against the live database (2 roles, 45 permissions). Confirm with
`php artisan tinker --execute="echo \App\Models\Role::count();"` before
migrating — if it prints 0, the migration would burn its one shot as a silent
no-op and the gate would lock everyone out.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PanelAccessTest`
Expected: PASS, 4 tests.

- [ ] **Step 7: Prove the gate test is real**

Change `canAccessPanel()` back to `return true;`, re-run, and confirm `test_a_user_with_no_roles_cannot_access_the_panel` and `test_a_roleless_user_is_bounced_from_the_panel_over_http` **fail**. Restore the permission check.

- [ ] **Step 8: Commit**

```bash
git add app/Models/User.php database/migrations tests/Feature/PanelAccessTest.php
git commit -m "feat: gate panel access on the admin.access permission"
```

---

### Task 4: Resource policies

**Files:**
- Create: `app/Policies/BlogPostPolicy.php`, `PagePolicy.php`, `BlogCategoryPolicy.php`, `ContactMessagePolicy.php`, `UserPolicy.php`, `CodeSnippetPolicy.php`, `RolePolicy.php`, `PermissionPolicy.php`
- Test: `tests/Feature/AuthorizationPolicyTest.php`

**Interfaces:**
- Consumes: the catalogue's permission names, `HasRoles`.
- Produces: policies discovered automatically by Laravel (`App\Policies\<Model>Policy` beside `App\Models\<Model>`), which Filament consults through `Resource::canCreate()`, `canEdit()`, `canDelete()` and `canViewAny()`.

**Note on model namespaces:** `BlogPost` and `BlogCategory` come from the `ajaydhakal/filament-story` package, not `App\Models`. Auto-discovery will not find policies for them. Register those two explicitly with `Gate::policy(...)` in `AppServiceProvider::boot()`, and say so in a comment. Verify each model's real FQCN with `php artisan model:show` or by reading the resource's `$model` property before writing the policy.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AuthorizationPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->editor = User::factory()->create();
        $this->editor->assignRole('content_editor');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_an_editor_may_edit_a_page_but_not_create_or_delete_one(): void
    {
        $page = Page::factory()->create();

        $this->assertTrue($this->editor->can('update', $page));
        $this->assertFalse($this->editor->can('create', Page::class));
        $this->assertFalse($this->editor->can('delete', $page));
    }

    public function test_a_super_admin_may_create_and_delete_pages(): void
    {
        $page = Page::factory()->create();

        $this->assertTrue($this->superAdmin->can('create', Page::class));
        $this->assertTrue($this->superAdmin->can('delete', $page));
    }

    public function test_an_editor_may_not_touch_users(): void
    {
        $this->assertFalse($this->editor->can('viewAny', User::class));
        $this->assertFalse($this->editor->can('create', User::class));
    }

    /**
     * A hidden nav link is not access control. This is the assertion that
     * proves typing the URL is refused.
     */
    public function test_an_editor_gets_403_on_the_users_screen_by_direct_url(): void
    {
        $this->actingAs($this->editor)
            ->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_an_editor_gets_403_on_the_code_snippets_screen_by_direct_url(): void
    {
        $this->actingAs($this->editor)
            ->get(\App\Filament\Resources\CodeSnippets\CodeSnippetResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_a_super_admin_reaches_the_users_screen(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))
            ->assertSuccessful();
    }
}
```

If `Page` has no factory, create one or seed a page the way `tests/Feature/Pages/` already does — check that directory first and follow it rather than inventing a setup.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuthorizationPolicyTest`
Expected: FAIL — with no policies, `can()` returns false for everyone, so the super-admin assertions fail.

- [ ] **Step 3: Write the eight policies**

Each has the same shape. `app/Policies/PagePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('pages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pages.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('pages.update');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('pages.delete');
    }
}
```

Write the remaining seven identically, swapping the model, the type hints and the permission prefix:

| Policy | Model | Prefix | Notes |
|---|---|---|---|
| `BlogPostPolicy` | the filament-story `BlogPost` | `posts` | register via `Gate::policy` |
| `BlogCategoryPolicy` | the filament-story `BlogCategory` | `categories` | register via `Gate::policy` |
| `ContactMessagePolicy` | `App\Models\ContactMessage` | `contacts` | **no `create` method** — there is no `contacts.create` permission |
| `UserPolicy` | `App\Models\User` | `users` | |
| `CodeSnippetPolicy` | `App\Models\CodeSnippet` | `code-snippets` | |
| `RolePolicy` | `App\Models\Role` | `roles` | all five methods return `$user->can('roles.manage')` |
| `PermissionPolicy` | `App\Models\Permission` | `permissions` | all five methods return `$user->can('permissions.manage')` |

`ContactMessagePolicy` omitting `create` means Filament's `canCreate()` falls through to denying — which is correct, since messages arrive from the public form.

- [ ] **Step 4: Register the two vendor-model policies**

In `app/Providers/AppServiceProvider::boot()`:

```php
// Laravel discovers App\Policies\<Model>Policy beside App\Models\<Model>.
// These two models live in the filament-story package, so nothing would be
// discovered for them and every check would silently fall through to denied.
Gate::policy(\AjayDhakal\FilamentStory\Models\BlogPost::class, \App\Policies\BlogPostPolicy::class);
Gate::policy(\AjayDhakal\FilamentStory\Models\BlogCategory::class, \App\Policies\BlogCategoryPolicy::class);
```

Confirm both FQCNs against the resources' `$model` properties before writing this.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AuthorizationPolicyTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Confirm the existing resource suites still pass**

Run: `php artisan test --filter="CodeSnippetResourceTest|BlogCategoryResourceTest|UserResourceTest"`
Expected: PASS. These act as a plain `User::factory()` user with no roles, so policies will now refuse them — **each will need its actor to be given a role**. Update those tests to assign `super_admin` in `setUp()`. That is a legitimate change: they were relying on there being no authorization at all.

Do **not** weaken a policy to keep an old test green.

- [ ] **Step 7: Prove the 403 test is real**

Delete `UserPolicy::viewAny()`, re-run, and confirm `test_a_super_admin_reaches_the_users_screen` **fails** (falls through to denied). Restore it. Then change `PagePolicy::create()` to `return true;` and confirm `test_an_editor_may_edit_a_page_but_not_create_or_delete_one` **fails**. Restore.

- [ ] **Step 8: Commit**

```bash
git add app/Policies app/Providers/AppServiceProvider.php tests/Feature/AuthorizationPolicyTest.php tests/Feature/Filament
git commit -m "feat: add resource policies for every permission-gated screen"
```

---

### Task 5: Navigation filtering

**Files:**
- Modify: `app/Filament/Navigation/AdminNavigation.php`
- Test: `tests/Feature/Filament/NavigationVisibilityTest.php`

**Interfaces:**
- Consumes: the catalogue's permission names.
- Produces: an `AdminNavigation` whose entries are filtered by permission, with parents pruned when all their children are hidden.

**Why this task exists:** `AdminNavigation`'s own docblock notes that a `NavigationBuilder` makes Filament skip auto-registration entirely. That also skips its policy-based nav hiding. Without this, a content editor sees Users, Settings and every System link, clicks one, and hits the 403 Task 4 added.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/NavigationVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sidebarFor(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->actingAs($user)->get('/superduper')->assertSuccessful()->getContent();
    }

    public function test_a_content_editor_sees_the_content_group_and_navigation_menus(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringContainsString('Posts', $html);
        $this->assertStringContainsString('Categories', $html);
        $this->assertStringContainsString('Navigation Menus', $html);
    }

    /**
     * Checked against rendered navigation rather than policy return values:
     * the NavigationBuilder is exactly what bypasses Filament's policy-based
     * hiding, so a passing policy test proves nothing about the sidebar.
     */
    public function test_a_content_editor_does_not_see_what_it_cannot_reach(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringNotContainsString('Code Snippets', $html);
        $this->assertStringNotContainsString('Site Settings', $html);
        $this->assertStringNotContainsString('Backups', $html);
        $this->assertStringNotContainsString('Roles', $html);
    }

    /**
     * An empty disclosure triangle is worse than no entry: it invites a click
     * that reveals nothing.
     */
    public function test_a_parent_with_no_visible_children_is_removed(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringNotContainsString('Marketing', $html);
        $this->assertStringNotContainsString('Users Management', $html);
    }

    public function test_a_super_admin_sees_everything(): void
    {
        $html = $this->sidebarFor('super_admin');

        $this->assertStringContainsString('Code Snippets', $html);
        $this->assertStringContainsString('Users Management', $html);
        $this->assertStringContainsString('Marketing', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NavigationVisibilityTest`
Expected: FAIL — the editor's sidebar still contains Code Snippets, Settings and Roles.

- [ ] **Step 3: Add a permission argument to the navigation helpers**

In `AdminNavigation`, give `item()`, `resource()`, `page()` and `pageUrl()` a required `string $permission` parameter, and pass the matching catalogue name at every call site. Then filter.

The filtering itself belongs in one place — a private helper that takes the built items and drops the ones the current user cannot use:

```php
    /**
     * Filament skips its own policy-based nav hiding when a NavigationBuilder
     * is used (see this class's docblock), so visibility is explicit here.
     * Without it an editor sees every link and learns the restriction by
     * hitting a 403.
     *
     * @param  array<int, NavigationItem>  $items
     * @return array<int, NavigationItem>
     */
    private static function visible(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (NavigationItem $item): bool => $item->isVisible(),
        ));
    }
```

Set each item's visibility with Filament's own `->visible(fn () => auth()->user()?->can($permission) ?? false)` inside the helpers, so a single mechanism drives both the item and the pruning.

A parent must be dropped when every child is hidden: build the children first, and return `null` (filtered out by the caller) when `visible($children)` is empty.

Consult the Filament 5 `NavigationItem` and `NavigationGroup` API for the exact visibility method names before writing this — do not assume they match Filament 3.

- [ ] **Step 4: Map every navigation entry to its permission**

| Entry | Permission |
|---|---|
| Dashboard | `dashboard.view` |
| Content › Posts | `posts.view` |
| Content › Pages | `pages.view` |
| Content › Content Blocks | `content-blocks.view` |
| Content › Categories | `categories.view` |
| Content › Comments | `comments.view` |
| Content › Media Library | `media.manage` |
| Contacts | `contacts.view` |
| Marketing › Newsletter | `newsletter.view` |
| Marketing › Announcements | `announcements.view` |
| Marketing › Advertisements | `advertisements.view` |
| Marketing › Ad Zones | `ad-zones.view` |
| Marketing › Social Posting | `social-posting.view` |
| Users Management › Users | `users.view` |
| Users Management › Roles | `roles.manage` |
| Users Management › Permissions | `permissions.manage` |
| Analytics | `analytics.view` |
| Email Activity | `email-activity.view` |
| SEO › Redirects | `redirects.view` |
| System › Code Snippets | `code-snippets.view` |
| System › Backups | `backups.view` |
| Appearance › Navigation Menus | `menus.manage` |
| Appearance › Pages | `template-pages.view` |
| Appearance › Template Settings | `template-settings.view` |
| Appearance › Translations | `translations.view` |
| Appearance › Theme Editor | `theme-editor.view` |
| Settings | `settings.manage` |

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=NavigationVisibilityTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Confirm the existing navigation suite still passes**

Run: `php artisan test --filter="AdminNavigationTest|OrderedResourcesTest|AdminShellStructureTest"`
Expected: PASS. These assert the sidebar's shape; their actor may now need `super_admin` assigned in `setUp()` to see everything. Assign the role — do not relax an assertion.

- [ ] **Step 7: Prove the visibility test is real**

Remove the `->visible(...)` call from the Code Snippets entry, re-run, and confirm `test_a_content_editor_does_not_see_what_it_cannot_reach` **fails**. Restore it.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Navigation/AdminNavigation.php tests/Feature/Filament
git commit -m "feat: filter the sidebar by permission"
```

---

### Task 6: The Roles screen

**Files:**
- Create: `app/Filament/Resources/Roles/RoleResource.php`
- Create: `app/Filament/Resources/Roles/Pages/ManageRoles.php`
- Modify: `app/Filament/Navigation/AdminNavigation.php`
- Delete: `app/Filament/Pages/Placeholders/RolesPlaceholder.php`
- Test: `tests/Feature/Filament/RoleResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\Role`, `PermissionCatalogue::groupLabel()`, `RolePolicy`.
- Produces: a simple resource — `getPages()` returns only `index => Pages\ManageRoles::route('/')` using `Filament\Resources\Pages\ManageRecords`, so create/edit/delete happen in modals.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/RoleResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    public function test_the_index_page_renders_and_lists_the_seeded_roles(): void
    {
        $this->get(RoleResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('super_admin')
            ->assertSee('content_editor');
    }

    public function test_it_creates_a_role_with_permissions(): void
    {
        Livewire::test(ManageRoles::class)
            ->callAction('create', data: [
                'name' => 'proofreader',
                'description' => 'Reads things',
                'permissions' => [\App\Models\Permission::findByName('posts.view')->getKey()],
            ])
            ->assertHasNoActionErrors();

        $role = Role::findByName('proofreader');

        $this->assertSame('Reads things', $role->description);
        $this->assertFalse($role->is_system, 'Roles made in the UI must stay deletable.');
        $this->assertTrue($role->hasPermissionTo('posts.view'));
    }

    public function test_the_role_name_is_required(): void
    {
        Livewire::test(ManageRoles::class)
            ->callAction('create', data: ['name' => null])
            ->assertHasActionErrors(['name' => 'required']);
    }

    /**
     * Deleting super_admin — or any system role — strips the only route back
     * into the panel for whoever holds it.
     */
    public function test_a_system_role_cannot_be_deleted(): void
    {
        $superAdmin = Role::findByName('super_admin');

        Livewire::test(ManageRoles::class)
            ->assertTableActionHidden('delete', $superAdmin);

        $this->assertTrue(Role::query()->whereKey($superAdmin->getKey())->exists());
    }

    public function test_a_normal_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'temporary']);

        Livewire::test(ManageRoles::class)
            ->callTableAction('delete', $role)
            ->assertHasNoTableActionErrors();

        $this->assertFalse(Role::query()->whereKey($role->getKey())->exists());
    }
}
```

Filament 5's action-testing helper names may differ from those above (`callAction`, `assertTableActionHidden`, `callTableAction`). Check `vendor/filament/actions/src/Testing/` and `vendor/filament/tables/src/Testing/` and use the real ones — but keep each assertion's *intent* exactly as written.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RoleResourceTest`
Expected: FAIL — `Class "App\Filament\Resources\Roles\RoleResource" not found`.

- [ ] **Step 3: Write the resource**

Create `app/Filament/Resources/Roles/RoleResource.php`. Key requirements, all from the spec:

- `protected static ?string $model = Role::class;` and `$navigationIcon = 'heroicon-o-shield-check'`. No `$navigationGroup`, no `$navigationSort`.
- **Table columns:** Name (with a `System` badge when `is_system`); Permissions as a count badge plus the first three permission names as chips and a `+N` overflow; Users as a `users_count` with a people icon; Created as a date.
- Use `->withCount(['permissions', 'users'])` on the table query so the counts do not fire a query per row.
- **Row actions:** `EditAction` and `DeleteAction` in a menu, with `DeleteAction::make()->hidden(fn (Role $record): bool => $record->is_system)`.
- **Header action:** `CreateAction` labelled "Add Role".
- **The form** (used by both modals): `TextInput::make('name')->required()`; `TextInput::make('description')->label('Description (Optional)')`; then a `CheckboxList::make('permissions')` with `->relationship('permissions', 'name')`, `->bulkToggleable()` (this is Filament's Select All), `->searchable()`, and `->descriptions()` or grouping such that options are grouped by `PermissionCatalogue::groupLabel()`. A flat list of forty-five is unusable.
- `getPages()` returns only `'index' => Pages\ManageRoles::route('/')`.

- [ ] **Step 4: Write the page class**

Create `app/Filament/Resources/Roles/Pages/ManageRoles.php`:

```php
<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    public function getHeading(): string
    {
        return 'Roles Management';
    }

    public function getSubheading(): ?string
    {
        return 'Manage user roles and their permissions';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Role'),
        ];
    }
}
```

- [ ] **Step 5: Swap the placeholder**

In `AdminNavigation`, replace
`self::page(P\RolesPlaceholder::class, 'Roles', 'heroicon-o-shield-check', 2, ...)`
with
`self::resource(RoleResource::class, 'Roles', 'heroicon-o-shield-check', 2, ...)`,
keeping the `roles.manage` permission from Task 5. Delete `app/Filament/Pages/Placeholders/RolesPlaceholder.php`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=RoleResourceTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Confirm the navigation suites still pass**

Run: `php artisan test --filter="AdminNavigationTest|NavigationVisibilityTest"`
Expected: PASS. The placeholder count drops by one — update the count, do not weaken the assertion.

- [ ] **Step 8: Prove the system-role guard is real**

Remove the `->hidden(...)` from `DeleteAction`, re-run, and confirm `test_a_system_role_cannot_be_deleted` **fails**. Restore it.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Roles app/Filament/Navigation/AdminNavigation.php tests/Feature/Filament/RoleResourceTest.php
git rm app/Filament/Pages/Placeholders/RolesPlaceholder.php
git commit -m "feat: add the roles admin screen"
```

---

### Task 7: The Permissions screen

**Files:**
- Create: `app/Filament/Resources/Permissions/PermissionResource.php`
- Create: `app/Filament/Resources/Permissions/Pages/ManagePermissions.php`
- Modify: `app/Filament/Navigation/AdminNavigation.php`
- Delete: `app/Filament/Pages/Placeholders/PermissionsPlaceholder.php`
- Test: `tests/Feature/Filament/PermissionResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\Permission`, `App\Models\Role`, `PermissionPolicy`.
- Produces: a simple resource, `index => Pages\ManagePermissions::route('/')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/PermissionResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Permissions\Pages\ManagePermissions;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    public function test_the_index_page_renders_and_lists_seeded_permissions(): void
    {
        $this->get(PermissionResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('admin.access');
    }

    public function test_it_creates_a_permission_and_assigns_it_to_roles(): void
    {
        $editor = Role::findByName('content_editor');

        Livewire::test(ManagePermissions::class)
            ->callAction('create', data: [
                'name' => 'exports.run',
                'roles' => [$editor->getKey()],
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($editor->fresh()->hasPermissionTo('exports.run'));
    }

    /**
     * Without this, every feature that adds a permission silently leaves the
     * top role missing an ability — the exact hole the no-wildcard decision
     * creates, and the reason it is closed here rather than in Gate::before.
     */
    public function test_a_new_permission_is_granted_to_every_system_role(): void
    {
        Livewire::test(ManagePermissions::class)
            ->callAction('create', data: ['name' => 'exports.run'])
            ->assertHasNoActionErrors();

        $this->assertTrue(Role::findByName('super_admin')->fresh()->hasPermissionTo('exports.run'));
    }

    public function test_the_permission_name_is_required(): void
    {
        Livewire::test(ManagePermissions::class)
            ->callAction('create', data: ['name' => null])
            ->assertHasActionErrors(['name' => 'required']);
    }

    /** Deleting admin.access locks every account out of the panel for good. */
    public function test_a_system_permission_cannot_be_deleted(): void
    {
        $gate = Permission::findByName('admin.access');

        Livewire::test(ManagePermissions::class)
            ->assertTableActionHidden('delete', $gate);

        $this->assertTrue(Permission::query()->whereKey($gate->getKey())->exists());
    }
}
```

As in Task 6, verify Filament 5's real action-testing helper names against `vendor/filament/*/src/Testing/` and keep each assertion's intent.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PermissionResourceTest`
Expected: FAIL — resource class not found.

- [ ] **Step 3: Write the resource**

Create `app/Filament/Resources/Permissions/PermissionResource.php`:

- `$model = Permission::class`, `$navigationIcon = 'heroicon-o-key'`. No group or sort.
- **Table columns:** Name (with a `System` badge when `is_system`); Roles as a count badge plus role-name chips and `+N`; Created as a date. `->withCount('roles')` on the query.
- **Row actions:** Edit and Delete, `DeleteAction::make()->hidden(fn (Permission $record): bool => $record->is_system)`.
- **Header action:** `CreateAction` labelled "Add Permission".
- **The form:** `TextInput::make('name')->required()->placeholder('e.g., manage.users')`; `CheckboxList::make('roles')->relationship('roles', 'name')->bulkToggleable()` — `bulkToggleable` is what renders the Select All / Unselect All controls the reference shows.
- **After create,** attach the new permission to every `is_system` role. Do this in the resource's create action via `->after(function (Permission $record): void { ... })`, granting it to `Role::query()->where('is_system', true)->get()`. Comment why: a permission the top role does not hold is an ability nobody can exercise.
- `getPages()` returns only `'index' => Pages\ManagePermissions::route('/')`.

- [ ] **Step 4: Write the page class**

Create `app/Filament/Resources/Permissions/Pages/ManagePermissions.php`:

```php
<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    public function getHeading(): string
    {
        return 'Permissions Management';
    }

    public function getSubheading(): ?string
    {
        return 'Manage user permissions and their assignments';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Permission'),
        ];
    }
}
```

- [ ] **Step 5: Swap the placeholder**

In `AdminNavigation`, replace the `PermissionsPlaceholder` entry with
`self::resource(PermissionResource::class, 'Permissions', 'heroicon-o-key', 3, ...)`, keeping the `permissions.manage` permission. Delete `app/Filament/Pages/Placeholders/PermissionsPlaceholder.php`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PermissionResourceTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Confirm the navigation suites still pass**

Run: `php artisan test --filter="AdminNavigationTest|NavigationVisibilityTest"`
Expected: PASS. Placeholder count drops by one again — correct it, do not weaken it.

- [ ] **Step 8: Prove the system-role grant is real**

Remove the `->after(...)` hook, re-run, and confirm `test_a_new_permission_is_granted_to_every_system_role` **fails**. Restore it.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Permissions app/Filament/Navigation/AdminNavigation.php tests/Feature/Filament/PermissionResourceTest.php
git rm app/Filament/Pages/Placeholders/PermissionsPlaceholder.php
git commit -m "feat: add the permissions admin screen"
```

---

### Task 8: The last-super-admin guard

**Files:**
- Create: `app/Models/Concerns/ProtectsTheLastSuperAdmin.php` (or an observer — see below)
- Modify: `app/Models/User.php`
- Test: `tests/Feature/LastSuperAdminGuardTest.php`

**Interfaces:**
- Consumes: `HasRoles`, `App\Models\Role`.
- Produces: a guard that refuses to remove the final `super_admin`, whichever route is taken.

**Why a model-level guard, not a UI check:** the Users screen (spec 3) is not built yet, and the demotion path will arrive there. A check that lives in a form is bypassed by tinker, a bulk action, or the screen that has not been written. This belongs where the change happens.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LastSuperAdminGuardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastSuperAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user->fresh();
    }

    /**
     * The panel keeps working until that session expires, and then nobody can
     * administer anything — including granting the role back.
     */
    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $last = $this->superAdmin();

        $this->expectException(\App\Exceptions\LastSuperAdminException::class);

        $last->removeRole('super_admin');
    }

    public function test_the_last_super_admin_cannot_be_deleted(): void
    {
        $last = $this->superAdmin();

        $this->expectException(\App\Exceptions\LastSuperAdminException::class);

        $last->delete();
    }

    public function test_a_super_admin_can_be_demoted_while_another_remains(): void
    {
        $keeper = $this->superAdmin();
        $leaver = $this->superAdmin();

        $leaver->removeRole('super_admin');

        $this->assertFalse($leaver->fresh()->hasRole('super_admin'));
        $this->assertTrue($keeper->fresh()->hasRole('super_admin'));
    }

    public function test_a_super_admin_can_be_deleted_while_another_remains(): void
    {
        $keeper = $this->superAdmin();
        $leaver = $this->superAdmin();

        $leaver->delete();

        $this->assertFalse(User::query()->whereKey($leaver->getKey())->exists());
        $this->assertTrue($keeper->fresh()->hasRole('super_admin'));
    }

    public function test_deleting_a_user_without_the_role_is_unaffected(): void
    {
        $this->superAdmin();

        $editor = User::factory()->create();
        $editor->assignRole('content_editor');

        $editor->delete();

        $this->assertFalse(User::query()->whereKey($editor->getKey())->exists());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LastSuperAdminGuardTest`
Expected: FAIL — `Class "App\Exceptions\LastSuperAdminException" not found`.

- [ ] **Step 3: Write the exception**

Create `app/Exceptions/LastSuperAdminException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation would leave the installation with no super_admin.
 *
 * The panel would keep working until the last session expired, and then
 * nobody could administer anything — including granting the role back.
 */
class LastSuperAdminException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This is the last super admin. Grant the role to another user before removing it from this one.');
    }
}
```

- [ ] **Step 4: Write the guard**

Add to `App\Models\User` a `deleting` model event in `booted()` that throws when the user holds `super_admin` and is the last to do so, and override `removeRole()` to do the same before delegating to the trait.

```php
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if ($user->isLastSuperAdmin()) {
                throw LastSuperAdminException::make();
            }
        });
    }

    /**
     * Guarded at the model rather than in a form: a form check is bypassed by
     * tinker, by a bulk action, and by the Users screen that has not been
     * built yet. This belongs where the change actually happens.
     */
    public function isLastSuperAdmin(): bool
    {
        if (! $this->hasRole('super_admin')) {
            return false;
        }

        return Role::findByName('super_admin')->users()->count() <= 1;
    }

    public function removeRole($role): static
    {
        $name = $role instanceof Role ? $role->name : $role;

        if ($name === 'super_admin' && $this->isLastSuperAdmin()) {
            throw LastSuperAdminException::make();
        }

        return parent::removeRole($role);
    }
```

Check the trait's real `removeRole` signature in `vendor/spatie/laravel-permission/src/Traits/HasRoles.php` and match it exactly — a mismatched signature is a fatal error, not a failing test.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=LastSuperAdminGuardTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Confirm nothing else broke**

Run: `php artisan test --filter="UserResourceTest|PanelAccessTest|RolesAndPermissionsSeederTest"`
Expected: PASS.

- [ ] **Step 7: Prove both guards are real**

Remove the `deleting` hook, re-run, confirm `test_the_last_super_admin_cannot_be_deleted` **fails**, restore. Then remove the `removeRole` override, confirm `test_the_last_super_admin_cannot_be_demoted` **fails**, restore.

- [ ] **Step 8: Commit**

```bash
git add app/Exceptions/LastSuperAdminException.php app/Models/User.php tests/Feature/LastSuperAdminGuardTest.php
git commit -m "feat: refuse to remove the last super admin"
```

---

## Self-review notes

Checked against `docs/superpowers/specs/2026-08-06-roles-permissions-design.md`:

| Spec section | Task |
|---|---|
| Foundation, extra columns, model swap | 1 |
| Permission catalogue, two roles, seeding | 2 |
| `super_admin` and future permissions | 2 (seeder re-sync) and 7 (create hook) |
| Panel access gate | 3 |
| Lockout migration + the test that ignores it | 3 |
| Policies | 4 |
| Navigation filtering | 5 |
| Roles screen, System badge, delete guard | 6 |
| Permissions screen, Select All, delete guard | 7 |
| Last-super-admin guard | 8 |
| Testing section | spread across 1–8 |

**One spec item deliberately not given its own task:** "`admin.access` cannot be detached from `super_admin`". Task 2's seeder re-syncs `super_admin` to hold every permission on each run, and Task 7's create hook grants new ones — so the only way to detach it is editing `super_admin` in the Roles UI and unchecking it. Task 6's form is the place to prevent that; the implementer should disable the `admin.access` checkbox when editing an `is_system` role, and it is called out here so it is not lost between tasks.

**Two API surfaces the plan cannot pin down from outside:** Filament 5's action-testing helper names (Tasks 6 and 7) and its `NavigationItem` visibility API (Task 5). Both tasks say to verify against `vendor/` rather than assume Filament 3 names, and to preserve each assertion's intent when the helper differs.
