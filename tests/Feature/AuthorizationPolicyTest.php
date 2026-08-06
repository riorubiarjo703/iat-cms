<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->editor = User::factory()->create();
        $this->editor->assignRole('content_editor');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    /**
     * Page has no factory (tests/Feature/Pages/PageRenderTest builds pages
     * the same way) — Page::factory() would throw rather than fail the
     * assertion, which would be a confusing way for this test to break.
     */
    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'About Us'],
            'slug' => 'about-us',
            'content' => ['en' => '<p>Body copy.</p>'],
            'status' => Page::STATUS_PUBLISHED,
        ], $attributes));
    }

    public function test_an_editor_may_edit_a_page_but_not_create_or_delete_one(): void
    {
        $page = $this->page();

        $this->assertTrue($this->editor->can('update', $page));
        $this->assertFalse($this->editor->can('create', Page::class));
        $this->assertFalse($this->editor->can('delete', $page));
    }

    public function test_a_super_admin_may_create_and_delete_pages(): void
    {
        $page = $this->page();

        $this->assertTrue($this->superAdmin->can('create', Page::class));
        $this->assertTrue($this->superAdmin->can('delete', $page));
    }

    public function test_an_editor_may_not_touch_users(): void
    {
        $this->assertFalse($this->editor->can('viewAny', User::class));
        $this->assertFalse($this->editor->can('create', User::class));
    }

    /**
     * A hidden nav link is not access control. This is the assertion that
     * proves typing the URL is refused.
     */
    public function test_an_editor_gets_403_on_the_users_screen_by_direct_url(): void
    {
        $this->actingAs($this->editor)
            ->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_an_editor_gets_403_on_the_code_snippets_screen_by_direct_url(): void
    {
        $this->actingAs($this->editor)
            ->get(\App\Filament\Resources\CodeSnippets\CodeSnippetResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_a_super_admin_reaches_the_users_screen(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))
            ->assertSuccessful();
    }
}
