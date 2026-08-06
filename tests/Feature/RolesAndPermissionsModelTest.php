<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesAndPermissionsModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Calling a static method directly on App\Models\Role (e.g. Role::create,
     * Role::findByName) always returns App\Models\Role regardless of config —
     * `static::` resolves to whichever class the call was made on, not to
     * whatever config('permission.models.role') says. That made an earlier
     * version of this test pass even with the config pointed back at
     * Spatie's own class. The registrar is what Spatie itself, and Laravel's
     * policy auto-discovery, actually consult — so assert against that.
     */
    public function test_the_permission_registrar_resolves_the_app_role_and_permission_classes(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $this->assertSame(Role::class, $registrar->getRoleClass());
        $this->assertSame(Permission::class, $registrar->getPermissionClass());
    }

    /**
     * The relations built by HasRoles/HasPermissions are populated through
     * the configured model, not a static call the test controls — so unlike
     * the registrar check above, this also proves the wiring holds up along
     * the path the app actually uses at runtime.
     */
    public function test_a_users_role_and_a_roles_permission_resolve_to_the_app_models(): void
    {
        $permission = Permission::create(['name' => 'posts.view']);
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertInstanceOf(Role::class, $user->roles->first());
        $this->assertInstanceOf(Permission::class, $role->permissions->first());
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
