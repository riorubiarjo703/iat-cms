<?php

namespace Tests\Feature;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
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

    /**
     * The spec says the component "outputs nothing at all — not even
     * whitespace" when a position has no snippets, so a page with none
     * configured never carries a stray blank line in its markup.
     */
    public function test_the_component_emits_nothing_when_no_snippets_match(): void
    {
        $this->assertSame('', view('components.code-snippets', ['position' => 'head'])->render());
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
