<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
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
                'permissions' => [Permission::findByName('posts.view')->getKey()],
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
            ->assertTableActionHidden('delete', record: $superAdmin);

        $this->assertTrue(Role::query()->whereKey($superAdmin->getKey())->exists());
    }

    public function test_a_normal_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'temporary']);

        Livewire::test(ManageRoles::class)
            ->callTableAction('delete', record: $role)
            ->assertHasNoTableActionErrors();

        $this->assertFalse(Role::query()->whereKey($role->getKey())->exists());
    }

    /**
     * admin.access is the panel gate, and super_admin is the only role that
     * guarantees a way back in. Every other path that could detach it is
     * already closed (the seeder re-syncs it, Task 7's create hook grants it
     * to new system roles), so this form is the last place a super_admin
     * could be edited into locking everyone out. Submitting a permissions
     * list that omits it — exactly what an unchecked box in the UI would
     * produce — must not be able to remove it.
     */
    public function test_admin_access_cannot_be_stripped_from_a_system_role(): void
    {
        $superAdmin = Role::findByName('super_admin');

        $remaining = $superAdmin->permissions()
            ->where('name', '!=', 'admin.access')
            ->pluck('permissions.id')
            ->all();

        Livewire::test(ManageRoles::class)
            ->callTableAction('edit', record: $superAdmin, data: [
                'name' => $superAdmin->name,
                'description' => $superAdmin->description,
                'permissions' => $remaining,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(
            $superAdmin->fresh()->hasPermissionTo('admin.access'),
            'super_admin lost admin.access — the panel would lock everyone out.',
        );
    }
}
