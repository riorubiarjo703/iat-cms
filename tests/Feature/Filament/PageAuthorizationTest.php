<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\BuildPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditMenuPage;
use App\Filament\Pages\MediaManagerPage;
use App\Filament\Pages\NavigationMenusPage;
use App\Filament\Pages\Placeholders\AnalyticsPlaceholder;
use App\Filament\Pages\SiteSettingsPage;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * C1: Filament's Page::canAccess() defaults to `true`
 * (vendor/filament/filament/src/Pages/Concerns/CanAuthorizeAccess.php), and
 * nothing in app/Filament/ overrode it. Resource policies only cover models,
 * so more than half the sidebar's destinations — Pages, not Resources — were
 * reachable by anyone who cleared the panel gate, no matter what the sidebar
 * hid. This proves each one now refuses over real HTTP, not merely a policy
 * return value.
 */
class PageAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function contentEditor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('content_editor');

        return $user->fresh();
    }

    /**
     * Holds the panel gate and nothing else — the only way to prove a page's
     * own canAccess() is doing the work, rather than the panel gate that
     * already runs before it. content_editor holds dashboard.view, so it
     * cannot be used to prove Dashboard's guard specifically.
     */
    private function bareGateHolder(): User
    {
        $role = Role::create(['name' => 'gate_only']);
        $role->givePermissionTo(Permission::findByName('admin.access'));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_a_content_editor_is_forbidden_from_site_settings(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(SiteSettingsPage::getUrl())
            ->assertForbidden();
    }

    public function test_a_super_admin_can_reach_site_settings(): void
    {
        $this->actingAsSuperAdmin()
            ->get(SiteSettingsPage::getUrl())
            ->assertSuccessful();
    }

    public function test_a_bare_gate_holder_is_forbidden_from_the_dashboard(): void
    {
        $this->actingAs($this->bareGateHolder())
            ->get(Dashboard::getUrl())
            ->assertForbidden();
    }

    public function test_a_content_editor_can_reach_the_dashboard(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(Dashboard::getUrl())
            ->assertSuccessful();
    }

    public function test_a_content_editor_is_forbidden_from_a_placeholder_it_holds_no_permission_for(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(AnalyticsPlaceholder::getUrl())
            ->assertForbidden();
    }

    public function test_a_super_admin_can_reach_a_placeholder(): void
    {
        $this->actingAsSuperAdmin()
            ->get(AnalyticsPlaceholder::getUrl())
            ->assertSuccessful();
    }

    public function test_a_content_editor_can_reach_navigation_menus(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(NavigationMenusPage::getUrl())
            ->assertSuccessful();
    }

    public function test_a_bare_gate_holder_is_forbidden_from_navigation_menus(): void
    {
        $this->actingAs($this->bareGateHolder())
            ->get(NavigationMenusPage::getUrl())
            ->assertForbidden();
    }

    public function test_a_bare_gate_holder_is_forbidden_from_the_menu_editor(): void
    {
        $menu = Menu::create(['name' => 'Main']);

        $this->actingAs($this->bareGateHolder())
            ->get(EditMenuPage::getUrl(['record' => $menu->getKey()]))
            ->assertForbidden();
    }

    public function test_a_content_editor_can_reach_the_menu_editor(): void
    {
        $menu = Menu::create(['name' => 'Main']);

        $this->actingAs($this->contentEditor())
            ->get(EditMenuPage::getUrl(['record' => $menu->getKey()]))
            ->assertSuccessful();
    }

    /**
     * BuildPage gates on pages.update, not pages.view — it edits a page's
     * content, the same ability PagePolicy::update() names.
     */
    private function page(): Page
    {
        return Page::create([
            'title' => ['en' => 'A Page'],
            'slug' => 'a-page',
            'content' => ['en' => '<p>Body copy.</p>'],
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    public function test_a_bare_gate_holder_is_forbidden_from_the_page_builder(): void
    {
        $this->actingAs($this->bareGateHolder())
            ->get(BuildPage::getUrl(['record' => $this->page()->getKey()]))
            ->assertForbidden();
    }

    public function test_a_content_editor_can_reach_the_page_builder(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(BuildPage::getUrl(['record' => $this->page()->getKey()]))
            ->assertSuccessful();
    }

    /**
     * The vendor Slimani\MediaManager\Pages\MediaManager page cannot be
     * edited to add a canAccess() of its own — App\Filament\Pages\MediaManagerPage
     * is the thin subclass registered in its place (AdminPanelProvider, via
     * MediaManagerPlugin::mediaManagerPage()). getUrl() is called on the
     * vendor class deliberately, matching every real call site
     * (AdminNavigation, the QuickActions widget) — proving the route those
     * already resolve now lands on a page that actually checks.
     */
    public function test_a_bare_gate_holder_is_forbidden_from_the_media_manager(): void
    {
        $this->actingAs($this->bareGateHolder())
            ->get(\Slimani\MediaManager\Pages\MediaManager::getUrl())
            ->assertForbidden();
    }

    public function test_a_content_editor_can_reach_the_media_manager(): void
    {
        $this->actingAs($this->contentEditor())
            ->get(MediaManagerPage::getUrl())
            ->assertSuccessful();
    }
}
