<?php

namespace App\Support;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use Illuminate\Support\Collection;

/**
 * Resolves which snippets belong at each injection point for this request.
 *
 * Both public layouts ask for three positions each, so a naive implementation
 * would run six queries per page. The whole active set is fetched once and
 * grouped instead — it is a handful of rows, and the request cache keeps it to
 * one query no matter how many positions ask.
 */
final class SnippetRenderer
{
    /** @return Collection<int, CodeSnippet> */
    public function for(SnippetPosition $position): Collection
    {
        $skipping = $this->shouldSkipForCurrentUser();

        return $this->grouped()
            ->get($position->value, collect())
            ->reject(fn (CodeSnippet $snippet) => $skipping && $snippet->skip_for_admins)
            ->values();
    }

    /**
     * Whether snippets flagged `skip_for_admins` should be withheld.
     *
     * Every account on this panel is currently a full administrator, so being
     * signed in is exactly the condition the flag describes. When roles land,
     * this is the one method that changes.
     */
    public function shouldSkipForCurrentUser(): bool
    {
        return auth()->check();
    }

    /**
     * Active snippets keyed by position value.
     *
     * The admin filter is applied after this point, not inside it — the cached
     * value must not depend on who is signed in.
     *
     * @return Collection<string, Collection<int, CodeSnippet>>
     */
    private function grouped(): Collection
    {
        return RequestCache::remember('code_snippets', fn () => CodeSnippet::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CodeSnippet $snippet) => $snippet->position->value));
    }
}
