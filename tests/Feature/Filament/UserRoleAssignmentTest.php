<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * UserResource::rolesField() must save through User::assignRole()/removeRole()
 * — the guarded methods — never through a relationship()-bound CheckboxList,
 * whose sync() call would bypass LastSuperAdminException entirely and let an
 * operator strip the installation's only super_admin.
 *
 * Kept separate from UserResourceTest: that suite covers the plain CRUD form
 * (name/email/password) and already has its own setUp() acting as a bare
 * super_admin. This one needs several distinct actors per test — a second
 * super_admin, a custodian with roles.manage but not users.update, an editor
 * with users.update but not roles.manage — which a shared setUp() would only
 * obscure.
 */
class UserRoleAssignmentTest extends TestCase
{
    use ActsAsSuperAdmin;
    use RefreshDatabase;

    public function test_assigning_a_role_through_the_form_attaches_it(): void
    {
        $this->actingAsSuperAdmin();

        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [Role::findByName('content_editor')->getKey()],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('content_editor'));
    }

    public function test_removing_a_role_through_the_form_detaches_it_when_another_super_admin_remains(): void
    {
        $this->actingAsSuperAdmin();

        // A second super_admin: removing the target's role must be allowed
        // because the acting user is not the only one left holding it.
        $target = $this->superAdmin();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($target->fresh()->hasRole('super_admin'));
    }

    /**
     * Mutation-proved: removing the try/catch around removeRole() in
     * UserResource::rolesField() turns this into an uncaught 500 and the
     * assertHasFormErrors(['roles']) call fails. See
     * users-role-assignment-report.md for the actual output.
     */
    public function test_removing_the_last_super_admins_role_is_refused(): void
    {
        // The only super_admin in this test — the guard this proves exists
        // specifically to protect.
        $target = $this->superAdmin();

        // A distinct actor who can reach and edit this form (users.update)
        // and is allowed to assign roles (roles.manage), but does not hold
        // super_admin — the refusal must come from the guard, not merely
        // from the acting user lacking the role themselves.
        $actor = User::factory()->create();
        $custodian = Role::create(['name' => 'role_custodian']);
        $custodian->givePermissionTo(['admin.access', 'roles.manage', 'users.update']);
        $actor->assignRole('role_custodian');
        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [],
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($target->fresh()->hasRole('super_admin'), 'The last super_admin lost the role.');
    }

    /**
     * Mutation-proved: dropping the auth()->user()->can('roles.manage')
     * check from inside saveRelationshipsUsing (leaving only ->visible())
     * makes this test fail, because Livewire::fillForm() sets the field's
     * state directly regardless of what is rendered. See
     * users-role-assignment-report.md for the actual output.
     */
    public function test_an_operator_without_roles_manage_cannot_change_roles_through_a_submitted_payload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $target = User::factory()->create();
        $target->assignRole('content_editor');

        // Can reach and edit the Users screen (users.update), but is not
        // trusted to hand out roles.
        $actor = User::factory()->create();
        $editorAdmin = Role::create(['name' => 'user_editor']);
        $editorAdmin->givePermissionTo(['admin.access', 'users.update']);
        $actor->assignRole('user_editor');
        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [Role::findByName('super_admin')->getKey()],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('content_editor'), 'The role this user started with must survive.');
        $this->assertFalse($fresh->hasRole('super_admin'), 'A payload from an operator lacking roles.manage took effect.');
    }
}
