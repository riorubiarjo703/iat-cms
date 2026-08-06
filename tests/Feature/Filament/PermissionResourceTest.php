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
