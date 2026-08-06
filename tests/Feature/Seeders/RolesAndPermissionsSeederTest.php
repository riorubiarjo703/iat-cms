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
