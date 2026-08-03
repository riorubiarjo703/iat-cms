<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarUserCardTest extends TestCase
{
    use RefreshDatabase;

    private function panel(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(User::factory()->create([
            'name' => 'Rio Rubiarjo',
            'email' => 'rio@example.com',
        ]))->get('/superduper');
    }

    public function test_the_sidebar_shows_the_signed_in_user(): void
    {
        $this->panel()
            ->assertSuccessful()
            ->assertSee('fi-sidebar-user', false)
            ->assertSee('Rio Rubiarjo')
            ->assertSee('rio@example.com');
    }

    public function test_the_avatar_uses_the_users_initials(): void
    {
        $this->panel()->assertSee('>RR<', false);
    }

    public function test_the_menu_offers_settings_change_password_and_sign_out(): void
    {
        $response = $this->panel();

        $response->assertSee('Settings');
        $response->assertSee('Change Password');
        $response->assertSee('Sign out');
    }

    public function test_the_change_password_link_points_at_the_profile_page(): void
    {
        Filament::setCurrentPanel('admin');

        $this->assertTrue(Filament::hasProfile(), 'Profile page is not enabled; Change Password would have no destination');
        $this->panel()->assertSee(Filament::getProfileUrl(), false);
    }

    public function test_the_profile_page_is_reachable_and_can_change_a_password(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->create())
            ->get(Filament::getProfileUrl())
            ->assertSuccessful()
            ->assertSee('Password');
    }

    public function test_sign_out_posts_rather_than_linking(): void
    {
        // A GET logout would be triggerable by any cross-site image tag.
        $this->panel()->assertSee('method="POST"', false);
    }

    public function test_every_sidebar_item_renders_an_icon(): void
    {
        // Upstream Filament suppresses icons on sub-grouped items, showing a
        // connecting bullet instead. The panel overrides that view. If the
        // override is lost in a Filament upgrade, icons < buttons and this fails.
        $html = $this->panel()->getContent();

        $this->assertSame(
            substr_count($html, 'fi-sidebar-item-btn'),
            substr_count($html, 'fi-sidebar-item-icon'),
            'Some sidebar items rendered without an icon',
        );
    }

    public function test_the_six_nested_parents_are_collapsible(): void
    {
        $html = $this->panel()->getContent();

        // Content, Marketing, Users Management, SEO, System, Appearance.
        $this->assertSame(6, substr_count($html, 'fi-sidebar-sub-group-items'));
        $this->assertSame(6, substr_count($html, 'fi-sidebar-item-chevron'));

        // x-show="expanded" is unique to the override. Asserting on bare
        // 'x-collapse' would pass regardless, because Filament's own group
        // markup uses it too — that weaker assertion did not fail under
        // mutation, which is why it is written this way.
        $this->assertSame(6, substr_count($html, 'x-show="expanded"'));
    }
}
