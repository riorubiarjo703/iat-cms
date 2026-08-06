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
