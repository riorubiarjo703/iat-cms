<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function panel(): \Filament\Panel
    {
        return Filament::getPanel('admin');
    }

    /**
     * The lockout migration grants super_admin only to three named operator
     * accounts, not to every user — the local test login and factory
     * accounts keep their logins but lose panel access. A factory-made user
     * in a normal test therefore has no role and no access either way, which
     * would make a gate stuck at `true` indistinguishable from a working
     * one. This asserts the refusal directly.
     */
    public function test_a_user_with_no_roles_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_a_super_admin_can_access_the_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue($user->fresh()->canAccessPanel($this->panel()));
    }

    public function test_a_content_editor_can_access_the_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('content_editor');

        $this->assertTrue($user->fresh()->canAccessPanel($this->panel()));
    }

    public function test_a_roleless_user_is_bounced_from_the_panel_over_http(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/superduper')->assertForbidden();
    }
}
