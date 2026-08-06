<?php

namespace Tests\Feature;

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
}
