<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * The reveal is position:sticky plus a z-index stack, which PHPUnit cannot
 * execute. What these hold is the contract the CSS depends on: both hooks
 * present in the markup, both defined in the stylesheet, and in the right
 * document order. Renaming either side alone breaks the effect silently and
 * completely — the page just looks normal.
 *
 * Whether it actually reveals is a browser check, and is Task 4 of
 * docs/superpowers/plans/2026-08-05-reveal-footer.md. These passing is not
 * evidence the footer reveals.
 */
class RevealFooterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    /**
     * Asserts the class appears as a whole token in a class attribute, so
     * renaming a hook to "scbd-shade-v2" fails rather than passing on the
     * substring. Mirrors ResponsiveMarkupTest.
     */
    private function assertHasClass(string $html, string $class): void
    {
        $this->assertMatchesRegularExpression(
            '/class="[^"]*(?<![\w-])'.preg_quote($class, '/').'(?![\w-])[^"]*"/',
            $html,
            "The [{$class}] reveal-footer hook is missing",
        );
    }

    private function home(): string
    {
        $this->seedHeaderMenu();
        $this->seedHomepage();

        return $this->get('/')->assertSuccessful()->getContent();
    }

    public function test_the_main_content_carries_the_shade_hook(): void
    {
        $this->assertHasClass($this->home(), 'scbd-shade');
    }

    public function test_the_footer_carries_the_sticky_hook(): void
    {
        $this->assertHasClass($this->home(), 'scbd-reveal-footer');
    }

    /**
     * The whole effect rests on the footer being a later sibling of the shade.
     * Sticky lifts the footer out of flow and the shade covers it only because
     * it paints over a lower z-index. Nest the footer inside <main>, or put it
     * above, and the reveal inverts.
     */
    public function test_the_footer_comes_after_the_main_content(): void
    {
        $html = $this->home();

        $mainEnd = strpos($html, '</main>');
        $footer = strpos($html, '<footer');

        $this->assertNotFalse($mainEnd, 'the main content should be present');
        $this->assertNotFalse($footer, 'the footer should be present');
        $this->assertGreaterThan(
            $mainEnd,
            $footer,
            'the footer must follow </main>, not nest inside it',
        );
    }

    /**
     * The hooks are inert without the rules that act on them, and the two live
     * in different files.
     */
    public function test_the_stylesheet_defines_the_reveal_rules(): void
    {
        $css = file_get_contents(resource_path('css/scbd.css'));

        $this->assertMatchesRegularExpression(
            '/\.scbd-reveal-footer\s*\{[^}]*position:\s*sticky/',
            $css,
            'the footer hook must be sticky',
        );
        $this->assertMatchesRegularExpression(
            '/\.scbd-shade\s*\{[^}]*z-index:\s*1/',
            $css,
            'the shade must stack above the footer',
        );
    }
}
