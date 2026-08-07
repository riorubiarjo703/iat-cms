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
