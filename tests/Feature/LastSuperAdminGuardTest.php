<?php

namespace Tests\Feature;

use App\Models\Role;
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

    /**
     * I1: with events_enabled => false, HasRoles::syncRoles() calls
     * detachRoles() directly and never reaches removeRole() — a role-set
     * replacement (exactly what a relationship-backed CheckboxList on the
     * Users screen would call) could demote the last super_admin with no
     * guard at all.
     */
    public function test_sync_roles_refuses_to_demote_the_last_super_admin(): void
    {
        $last = $this->superAdmin();

        $this->expectException(\App\Exceptions\LastSuperAdminException::class);

        $last->syncRoles(['content_editor']);
    }

    public function test_sync_roles_refuses_to_strip_every_role_from_the_last_super_admin(): void
    {
        $last = $this->superAdmin();

        $this->expectException(\App\Exceptions\LastSuperAdminException::class);

        $last->syncRoles([]);
    }

    /**
     * The other direction: flipping events_enabled to true was rejected
     * specifically because Spatie's event-enabled path removes before it
     * assigns, so this exact call — sync to a set that still includes
     * super_admin — would have thrown a false positive on that path. It must
     * not throw here.
     */
    public function test_sync_roles_does_not_throw_when_the_last_super_admin_keeps_the_role(): void
    {
        $last = $this->superAdmin();

        $last->syncRoles(['super_admin', 'content_editor']);

        $this->assertTrue($last->fresh()->hasRole('super_admin'));
        $this->assertTrue($last->fresh()->hasRole('content_editor'));
    }

    public function test_sync_roles_can_demote_a_super_admin_while_another_remains(): void
    {
        $keeper = $this->superAdmin();
        $leaver = $this->superAdmin();

        $leaver->syncRoles(['content_editor']);

        $this->assertFalse($leaver->fresh()->hasRole('super_admin'));
        $this->assertTrue($leaver->fresh()->hasRole('content_editor'));
        $this->assertTrue($keeper->fresh()->hasRole('super_admin'));
    }

    /**
     * C3(b): isLastSuperAdmin() and removeRole() resolve the protected role
     * by is_system, not the literal name "super_admin" — comparing by name
     * would stop protecting the role the moment it was renamed. The admin
     * screen itself now blocks a rename (RoleResourceTest), but this proves
     * the guard does not silently depend on that being the only path to one:
     * it survives a rename made any other way, e.g. directly on the model.
     */
    public function test_the_guard_survives_a_renamed_system_role(): void
    {
        $last = $this->superAdmin();

        Role::where('is_system', true)->update(['name' => 'renamed_super_admin']);

        $this->expectException(\App\Exceptions\LastSuperAdminException::class);

        $last->fresh()->removeRole('renamed_super_admin');
    }
}
