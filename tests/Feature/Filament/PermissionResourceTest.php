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

    /**
     * C2: this screen's `roles` CheckboxList used Filament's default
     * relationship save (a plain sync, which detaches). Editing admin.access
     * and unchecking super_admin removed the row from role_has_permissions —
     * exactly what an unchecked box in the browser submits, and exactly what
     * a crafted payload omitting super_admin's id produces here — and
     * nobody could enter the panel again. is_system only hid deletion; it
     * did nothing to stop this.
     */
    public function test_admin_access_cannot_be_detached_from_super_admin(): void
    {
        $gate = Permission::findByName('admin.access');
        $superAdmin = Role::findByName('super_admin');

        $remaining = $gate->roles()
            ->where('roles.name', '!=', 'super_admin')
            ->pluck('roles.id')
            ->all();

        Livewire::test(ManagePermissions::class)
            ->callTableAction('edit', record: $gate, data: [
                'name' => $gate->name,
                'roles' => $remaining,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(
            $superAdmin->fresh()->hasPermissionTo('admin.access'),
            'super_admin lost admin.access — the panel would lock everyone out.',
        );
    }

    /**
     * C3: renaming admin.access makes User::canAccessPanel()'s
     * can('admin.access') false for every account — a permanent lockout,
     * worse than deletion, which is guarded. The name field is disabled for
     * is_system rows, but a disabled field alone does not stop a payload
     * that sets it directly — dehydrated(false) is the half that actually
     * matters, and this submits exactly the payload a disabled-bypassing
     * request would send.
     */
    public function test_admin_access_permission_name_cannot_be_changed(): void
    {
        $gate = Permission::findByName('admin.access');

        Livewire::test(ManagePermissions::class)
            ->callTableAction('edit', record: $gate, data: [
                'name' => 'renamed.access',
                'roles' => $gate->roles()->pluck('roles.id')->all(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('admin.access', $gate->fresh()->name);
    }

    /** A normal permission's name must stay editable. */
    public function test_a_normal_permission_name_can_be_changed(): void
    {
        $permission = Permission::create(['name' => 'temporary.view']);

        Livewire::test(ManagePermissions::class)
            ->callTableAction('edit', record: $permission, data: [
                'name' => 'renamed.view',
                'roles' => [],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('renamed.view', $permission->fresh()->name);
    }
}
