<?php

namespace Tests\Feature\News;

use Tests\TestCase;

class NewsFilterContractTest extends TestCase
{
    /**
     * The filter module binds to attributes rather than classes, and nothing
     * in PHP would fail if a rename silently broke that binding — the page
     * would render perfectly and the chips would simply stop working. This
     * pins both halves of the contract in one place.
     */
    public function test_the_module_and_the_markup_agree_on_every_hook(): void
    {
        $module = file_get_contents(base_path('resources/js/scbd/newsFilter.js'));
        $view = file_get_contents(base_path('resources/views/partials/blocks/scbd-news-index.blade.php'));
        $card = file_get_contents(base_path('resources/views/partials/site/news-card.blade.php'));
        $markup = $view.$card;

        foreach (['data-news-filter', 'data-news-grid', 'data-news-filter-chip', 'data-news-category'] as $hook) {
            // Bracketed form, not bare substring: `data-news-filter` is a
            // substring of `data-news-filter-chip`, so a bare
            // assertStringContainsString would stay green even if the
            // module's actual `[data-news-filter]` selector were renamed —
            // the still-present `[data-news-filter-chip]` selector elsewhere
            // in the file would satisfy it. The module always uses hooks as
            // CSS attribute selectors, so the literal `[hook]` — including
            // the closing bracket — only matches the exact hook.
            $this->assertStringContainsString("[{$hook}]", $module, "newsFilter.js does not select [{$hook}]");

            // Markup emits hooks as bare HTML attributes, not bracketed
            // selectors, so the discriminator there is a negative lookahead:
            // the hook name not immediately followed by a hyphen. That is
            // false for `data-news-filter` when only `data-news-filter-chip`
            // is present, and true once the section's own `data-news-filter`
            // attribute exists.
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($hook, '/').'(?!-)/',
                $markup,
                "No view emits {$hook}"
            );
        }

        $this->assertStringContainsString('is-hidden', $module);
        $this->assertStringContainsString('.is-hidden', file_get_contents(base_path('resources/css/scbd.css')));
    }

    public function test_the_module_is_wired_into_the_bundle(): void
    {
        // A module nobody imports is dead code that still passes every test.
        $index = file_get_contents(base_path('resources/js/scbd/index.js'));

        $this->assertStringContainsString("from './newsFilter'", $index);
        $this->assertStringContainsString('initNewsFilter(', $index);
    }

    public function test_reduced_motion_still_filters(): void
    {
        // The chips are function, not decoration. Under reduced motion the
        // class toggle and the aria-pressed updates must still run — only the
        // Flip tween is skipped.
        //
        // There is no JS runner here and this file stays a source contract, so
        // the assertion has to be chosen carefully. Two earlier attempts were
        // not:
        //
        //   1. Asserting that "prefersReducedMotion" appears at all. The import
        //      line alone satisfied that.
        //   2. Asserting the class toggle appears textually BEFORE the
        //      `if (state)` tween guard. Inserting `if (reduced) return;` as
        //      the first statement of apply() — the exact regression this test
        //      names — leaves that ordering untouched, so it stayed green.
        //
        // What actually distinguishes "reduced motion skips only the tween"
        // from "reduced motion skips the filtering" is not ordering but reach:
        // how much of apply() the motion preference is allowed to touch. So the
        // contract is a counting one. Inside apply(), the identifier `reduced`
        // may be read exactly ONCE, and that one read must be the ternary that
        // decides whether to capture Flip state. Any second mention — an early
        // return, a conditional wrapped round the toggle loop, a guard on the
        // aria-pressed loop — means the preference is gating more than the
        // animation, and fails here regardless of where in the function it sits.
        $module = file_get_contents(base_path('resources/js/scbd/newsFilter.js'));

        $this->assertStringContainsString('prefersReducedMotion', $module);

        $body = $this->applyBody($module);

        // Proof that the extraction above really did capture the filtering
        // function, so the count below cannot pass by scanning nothing.
        $this->assertStringContainsString(
            "classList.toggle('is-hidden'",
            $body,
            "apply() no longer toggles 'is-hidden' on the cards",
        );
        $this->assertStringContainsString(
            'aria-pressed',
            $body,
            'apply() no longer updates aria-pressed on the chips',
        );
        $this->assertStringContainsString(
            'if (state)',
            $body,
            'apply() no longer gates the tween on `if (state)`',
        );

        // Comments are not code: a comment that happens to say "reduced" must
        // not fail this, and must not be able to satisfy it either.
        $code = $this->withoutComments($body);

        preg_match_all('/(?<![\w$])reduced(?![\w$])/', $code, $matches);

        $this->assertCount(
            1,
            $matches[0],
            'apply() reads `reduced` '.count($matches[0]).' times. It may read it exactly once, '
                ."to decide whether to capture Flip state. A second read means the motion preference \n"
                ."is gating the filtering itself, not just the animation. apply() body was:\n".$code,
        );

        $this->assertMatchesRegularExpression(
            '/const\s+state\s*=\s*reduced\s*\?\s*null\s*:/',
            $code,
            'The single use of `reduced` in apply() must be the `const state = reduced ? null : …` '
                .'ternary that gates the Flip capture, and nothing else.',
        );

        // Belt and braces on top of the count: the filtering still has to come
        // before the tween gate, not merely be ungated by `reduced`.
        $this->assertLessThan(
            strpos($body, 'if (state)'),
            strpos($body, "classList.toggle('is-hidden'"),
            'The is-hidden toggle must run before the motion guard, or reduced motion would skip the filtering itself.',
        );
    }

    /**
     * The source of apply()'s body, brace-matched from its opening `{`.
     *
     * Textual, because there is no JS runtime here to ask. The caller asserts
     * that the result contains the statements it expects, so a mis-extraction
     * shows up as a failure rather than as an empty string that trivially
     * satisfies every check made of it.
     */
    private function applyBody(string $module): string
    {
        $at = strpos($module, 'const apply = ');

        $this->assertNotFalse($at, 'newsFilter.js no longer defines `const apply = …`');

        $open = strpos($module, '{', $at);

        $this->assertNotFalse($open, 'The apply() definition in newsFilter.js has no body');

        $depth = 0;
        $length = strlen($module);

        for ($i = $open; $i < $length; $i++) {
            if ($module[$i] === '{') {
                $depth++;

                continue;
            }

            if ($module[$i] === '}' && --$depth === 0) {
                return substr($module, $open + 1, $i - $open - 1);
            }
        }

        $this->fail('The apply() body in newsFilter.js has unbalanced braces');
    }

    private function withoutComments(string $source): string
    {
        return (string) preg_replace('#//[^\n]*#', '', (string) preg_replace('#/\*.*?\*/#s', '', $source));
    }
}
