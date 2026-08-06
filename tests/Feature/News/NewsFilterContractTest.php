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
        // the assertions have to be chosen carefully. Three earlier attempts
        // were not:
        //
        //   1. Asserting that "prefersReducedMotion" appears at all. The import
        //      line alone satisfied that.
        //   2. Asserting the class toggle appears textually BEFORE the
        //      `if (state)` tween guard. Inserting `if (reduced) return;` as
        //      the first statement of apply() leaves that ordering untouched.
        //   3. Counting reads of the identifier `reduced` inside apply() and
        //      requiring exactly one. Defeated by `if (!state) return;` (which
        //      never names the preference at all, yet `state` is already null
        //      under reduced motion), and by hoisting `const noMotion = reduced`
        //      outside apply() and guarding on `noMotion` inside it.
        //
        // Counting tokens keeps losing because the property is not about how
        // often a name appears. It is about CONTROL FLOW. Under reduced motion,
        // apply() takes exactly the same path through the filtering as it does
        // under full motion, and only diverges at the tween. So the contract is
        // structural:
        //
        //   * Everything in apply() before the `if (state)` tween gate is
        //     straight-line: exactly three statements, in order — capture Flip
        //     state, toggle `is-hidden` on the cards, update `aria-pressed` on
        //     the chips. No early return, no branch, nothing wrapped in an
        //     `if`, whatever the condition is named or derived from.
        //   * The preference itself is read once, at module scope, and never
        //     inside apply().
        //   * The click listener does nothing but call apply(), so a guard
        //     cannot be smuggled up into the caller either.
        //
        // Anything that stops the toggle or the aria update from running under
        // reduced motion has to break one of those three, because there is
        // nowhere else for such a guard to live.
        $module = $this->withoutComments(file_get_contents(base_path('resources/js/scbd/newsFilter.js')));

        $this->assertStringContainsString('prefersReducedMotion', $module);

        $body = $this->applyBody($module);

        // Proof that the extraction above really did capture the filtering
        // function, so the structural checks below cannot pass by scanning
        // nothing.
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

        $gate = strpos($body, 'if (state)');

        $this->assertNotFalse($gate, 'apply() no longer gates the tween on `if (state)`');

        // The preference is a module-scope constant. Reading it again inside
        // apply() — under any name — is how a guard gets in.
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w$])prefersReducedMotion(?![\w$])/',
            $body,
            'apply() calls prefersReducedMotion() itself. The preference is read once at module scope; '
                .'re-reading it inside apply() is how a guard on the filtering gets smuggled in.',
        );

        // ------------------------------------------------------------------
        // The straight-line prelude.
        // ------------------------------------------------------------------
        $prelude = substr($body, 0, $gate);

        // Nothing before the tween gate may branch or bail out — not at
        // statement level and not inside the two forEach callbacks either.
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w$])(if|else|return|switch|matchMedia)(?![\w$])/',
            $prelude,
            "Everything in apply() before the `if (state)` tween gate must run unconditionally, so that \n"
                ."reduced motion skips the tween and nothing else. Found a branch or an early return in:\n"
                .$prelude,
        );

        preg_match_all('/(?<![\w$])reduced(?![\w$])/', $prelude, $reads);

        $this->assertCount(
            1,
            $reads[0],
            'The motion preference may be read exactly once before the tween gate — to decide whether to '
                .'capture Flip state. Found '.count($reads[0])." reads in:\n".$prelude,
        );

        $statements = $this->topLevelStatements($prelude);

        $expected = [
            // The ONE thing the motion preference is allowed to decide.
            '/^const\s+state\s*=\s*reduced\s*\?\s*null\s*:\s*Flip\.getState\(/',
            // The filtering itself: unconditional.
            '/^cards\.forEach\(.*classList\.toggle\(\'is-hidden\'/s',
            // The accessible state of the chips: also unconditional.
            '/^chips\.forEach\(.*aria-pressed/s',
        ];

        $rendered = implode("\n---\n", array_map(
            static fn (int $i, string $s): string => ($i + 1).': '.$s,
            array_keys($statements),
            $statements,
        ));

        $this->assertCount(
            count($expected),
            $statements,
            'Everything in apply() before the `if (state)` tween gate must be three straight-line '
                ."statements — capture Flip state, toggle is-hidden, update aria-pressed. Found "
                .count($statements).", which means a branch or an early return was added. Statements were:\n"
                .$rendered,
        );

        foreach ($expected as $i => $pattern) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $statements[$i],
                'Statement '.($i + 1).' before the tween gate in apply() is no longer the plain, unguarded '
                    ."statement it must be. Under reduced motion the filtering still has to run; only the \n"
                    ."tween may be skipped. Statements were:\n".$rendered,
            );
        }

        // ------------------------------------------------------------------
        // The caller.
        // ------------------------------------------------------------------
        // A straight-line apply() is worth nothing if the chip's click handler
        // decides not to call it.
        $handler = $this->clickHandler($module);

        $this->assertStringContainsString(
            'apply(',
            $handler,
            'The chip click listener no longer calls apply()',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w$])(if|return|reduced|prefersReducedMotion|matchMedia)(?![\w$])/',
            $handler,
            "The chip click listener must do nothing but call apply(). A guard there skips the filtering \n"
                ."just as surely as one inside apply(). Listener was:\n".$handler,
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

    /**
     * Split a run of JavaScript into its top-level statements.
     *
     * A statement ends at a `;` written at nesting depth zero, or at a `}` that
     * closes back to depth zero (a block statement such as `if (…) { … }`,
     * which carries no trailing semicolon). Braces, parentheses and brackets
     * all count towards the depth, so the semicolons inside a `forEach`
     * callback belong to that callback and not to the enclosing run.
     *
     * That is deliberately blunt about strings and template literals — the
     * module has no braces inside string literals, and the caller pins each
     * returned statement against an expected shape, so a mis-split surfaces as
     * a failure rather than as a silent pass.
     *
     * @return list<string>
     */
    private function topLevelStatements(string $code): array
    {
        $statements = [];
        $depth = 0;
        $start = 0;
        $length = strlen($code);

        for ($i = 0; $i < $length; $i++) {
            $char = $code[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ')' || $char === ']') {
                $depth--;

                if ($depth === 0 && $char === '}') {
                    $statements[] = substr($code, $start, $i - $start + 1);
                    $start = $i + 1;
                }

                continue;
            }

            if ($char === ';' && $depth === 0) {
                $statements[] = substr($code, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $statements[] = substr($code, $start);

        return array_values(array_filter(
            array_map(static fn (string $s): string => trim($s), $statements),
            static fn (string $s): bool => $s !== '',
        ));
    }

    /**
     * The argument list of the module's single `addEventListener(…)` call.
     *
     * The caller asserts it calls apply() and branches on nothing, which is
     * what stops a reduced-motion guard being moved out of apply() and into
     * the chip's click handler.
     */
    private function clickHandler(string $module): string
    {
        $at = strpos($module, 'addEventListener(');

        $this->assertNotFalse($at, 'newsFilter.js no longer binds a listener to the chips');
        $this->assertSame(
            1,
            substr_count($module, 'addEventListener('),
            'newsFilter.js binds more than one listener; this contract only inspects the first.',
        );

        $open = strpos($module, '(', $at);
        $depth = 0;
        $length = strlen($module);

        for ($i = $open; $i < $length; $i++) {
            if ($module[$i] === '(') {
                $depth++;

                continue;
            }

            if ($module[$i] === ')' && --$depth === 0) {
                return substr($module, $open + 1, $i - $open - 1);
            }
        }

        $this->fail('The addEventListener call in newsFilter.js has unbalanced parentheses');
    }

    private function withoutComments(string $source): string
    {
        return (string) preg_replace('#//[^\n]*#', '', (string) preg_replace('#/\*.*?\*/#s', '', $source));
    }
}
