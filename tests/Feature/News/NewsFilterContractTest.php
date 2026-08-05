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
        // class toggle must still run — only the tween is skipped.
        $module = file_get_contents(base_path('resources/js/scbd/newsFilter.js'));

        $this->assertStringContainsString('prefersReducedMotion', $module);
    }
}
