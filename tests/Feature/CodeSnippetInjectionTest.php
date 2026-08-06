<?php

namespace Tests\Feature;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class CodeSnippetInjectionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHomepage();
    }

    public function test_a_head_snippet_renders_inside_the_head(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::Head,
            'code' => '<meta name="verify" content="abc">',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('<meta name="verify" content="abc">', $head);
    }

    public function test_a_body_end_snippet_renders_after_the_head(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::BodyEnd,
            'code' => '<script>chat()</script>',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $this->assertGreaterThan(
            strpos($html, '</head>'),
            strpos($html, '<script>chat()</script>'),
        );
    }

    /**
     * `/` and every published page slug both render through `page.blade.php`
     * — `App\Http\Controllers\HomeController` and `PageController` both
     * `return view('page', [...])`, and that view's only layout tag is
     * `<x-layouts.page>`. `components/layouts/public.blade.php` has no route
     * that selects it anywhere in this app (confirmed by grepping routes,
     * controllers and views for `layouts.public` / `layouts::public`), so a
     * request to a CMS page slug would exercise the exact same layout as `/`
     * and prove nothing about the second file Step 5 edited.
     *
     * Since there is no HTTP path to `public.blade.php` today, this renders
     * it directly — the only way to prove its snippet insertion is real and
     * has not drifted from `page.blade.php`'s, which is the entire reason
     * the injection is a shared `<x-code-snippets>` component rather than
     * two hand-written blocks.
     */
    public function test_snippets_render_on_cms_pages_too(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::Head,
            'code' => '<meta name="verify" content="public-layout">',
        ]);

        $html = view('components.layouts.public', [
            'data' => (object) [
                'settings' => SiteSetting::singleton(),
                'i18n' => [],
            ],
            'slot' => 'content',
        ])->render();

        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('<meta name="verify" content="public-layout">', $head);
    }

    /**
     * The whole feature is emitting operator markup verbatim. If someone
     * "fixes" the `{!! !!}` in the component to `{{ }}`, every snippet on the
     * site silently becomes visible text instead of running. This is the test
     * that stops that.
     */
    public function test_snippet_code_is_emitted_unescaped(): void
    {
        CodeSnippet::factory()->create([
            'position' => SnippetPosition::Head,
            'code' => '<script>var x = 1 && 2;</script>',
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        $this->assertStringContainsString('<script>var x = 1 && 2;</script>', $html);
        $this->assertStringNotContainsString('&lt;script&gt;', $html);
    }

    public function test_inactive_snippets_do_not_reach_the_page(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>disabled()</script>',
            'is_active' => false,
        ]);

        $this->get('/')->assertSuccessful()->assertDontSee('disabled()', false);
    }

    public function test_admin_skipping_snippets_are_absent_for_signed_in_users(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>track()</script>',
            'skip_for_admins' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSuccessful()
            ->assertDontSee('track()', false);
    }

    public function test_the_admin_panel_never_renders_snippets(): void
    {
        CodeSnippet::factory()->create([
            'code' => '<script>track()</script>',
            'skip_for_admins' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/superduper')
            ->assertSuccessful()
            ->assertDontSee('track()', false);
    }
}
