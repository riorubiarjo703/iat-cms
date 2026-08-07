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

    /**
     * I4: the permissions checklist is grouped — one CheckboxList per
     * feature (Posts, Pages, Admin, ...) rather than one flat list of every
     * permission — so the create payload addresses a permission by
     * `permission_groups.{group-slug}`, not a single `permissions` array.
     */
    public function test_it_creates_a_role_with_permissions(): void
    {
        Livewire::test(ManageRoles::class)
            ->callAction('create', data: [
                'name' => 'proofreader',
                'description' => 'Reads things',
                'permission_groups' => [
                    'posts' => [Permission::findByName('posts.view')->getKey()],
                ],
            ])
            ->assertHasNoActionErrors();

        $role = Role::findByName('proofreader');

        $this->assertSame('Reads things', $role->description);
        $this->assertFalse($role->is_system, 'Roles made in the UI must stay deletable.');
        $this->assertTrue($role->hasPermissionTo('posts.view'));
    }

    /**
     * Each group's field saves only its own slice of the pivot
     * (RoleResource::permissionGroupField()) — proves that clearing one
     * group does not disturb permissions the role holds in a different
     * group, which a single shared `sync()` across the whole table would
     * have made trivially easy to get wrong.
     */
    public function test_editing_one_permission_group_does_not_disturb_another(): void
    {
        $role = Role::create(['name' => 'proofreader']);
        $role->givePermissionTo(['posts.view', 'pages.view']);

        Livewire::test(ManageRoles::class)
            ->callTableAction('edit', record: $role, data: [
                'name' => $role->name,
                'permission_groups' => ['posts' => []],
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $role->fresh();

        $this->assertFalse($fresh->hasPermissionTo('posts.view'), 'Unchecking the Posts group should detach it.');
        $this->assertTrue($fresh->hasPermissionTo('pages.view'), 'The untouched Pages group must survive.');
    }

    /**
     * I4: the reference put the group under each checkbox via
     * ->descriptions() — a caption, not a grouping. A flat list of
     * fifty-seven checkboxes is unusable, which is the whole reason this
     * section exists. Proven by rendering the create modal and finding
     * several group labels present as their own headings, each with more
     * than one associated option — not merely present anywhere on the page.
     */
    public function test_the_create_modal_groups_permissions_by_feature(): void
    {
        Livewire::test(ManageRoles::class)
            ->mountAction('create')
            ->assertMountedActionModalSee([
                'Posts', 'Pages', 'Categories', 'Admin',
                // Each of these dotted names is also a raw checkbox option
                // label, so this also checks grouping did not collapse back
                // into a single undifferentiated list.
                'posts.view', 'pages.view', 'admin.access',
            ]);
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

        Livewire::test(ManageRoles::class)
            ->callTableAction('edit', record: $superAdmin, data: [
                'name' => $superAdmin->name,
                'description' => $superAdmin->description,
                // admin.access is the only permission in the "Admin" group —
                // an empty array here is exactly what an unchecked box (the
                // only box in that group) submits.
                'permission_groups' => ['admin' => []],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(
            $superAdmin->fresh()->hasPermissionTo('admin.access'),
            'super_admin lost admin.access — the panel would lock everyone out.',
        );
    }

    /**
     * C3: renaming super_admin makes User::isLastSuperAdmin() and
     * removeRole() — which resolve the role by is_system now, not by this
     * literal name — irrelevant to the point, but before that fix they
     * resolved nothing and the guard silently stopped protecting anyone.
     * Renaming would also make the next seeder run create a *second*
     * all-permissions role via updateOrCreate(['name' => 'super_admin']).
     * The name field is disabled for is_system rows in the browser, but
     * dehydrated(false) is what actually stops a payload that sets it
     * directly, which is what this submits.
     */
    public function test_the_super_admin_role_name_cannot_be_changed(): void
    {
        $superAdmin = Role::findByName('super_admin');

        Livewire::test(ManageRoles::class)
            ->callTableAction('edit', record: $superAdmin, data: [
                'name' => 'renamed_admin',
                'description' => $superAdmin->description,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('super_admin', $superAdmin->fresh()->name);
    }

    /** A normal role's name must stay editable. */
    public function test_a_normal_role_name_can_be_changed(): void
    {
        $role = Role::create(['name' => 'temporary']);

        Livewire::test(ManageRoles::class)
            ->callTableAction('edit', record: $role, data: [
                'name' => 'renamed_role',
                'description' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('renamed_role', $role->fresh()->name);
    }
}
