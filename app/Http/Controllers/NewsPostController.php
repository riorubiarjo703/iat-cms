<?php

namespace App\Http\Controllers;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class NewsPostController extends Controller
{
    public function __invoke(string $slug): View
    {
        // Published means published now. A post marked published but dated in
        // the future is scheduled, not live, and must 404 rather than 403 — an
        // unlisted URL should not confirm that a post exists.
        $post = self::published()->where('slug', $slug)->firstOrFail();

        return view('news.show', [
            'post' => $post,
            // Reading order: "previous" is the post you would have read before
            // this one. Both walk the published set, so a draft between two
            // posts is stepped over rather than linked to a URL that 404s.
            'previous' => self::neighbour($post, 'desc'),
            'next' => self::neighbour($post, 'asc'),
            // Excludes the post being viewed: a reader must never be offered a
            // link back to the page they are already on.
            'latest' => self::published()
                ->whereKeyNot($post->getKey())
                ->orderByDesc('published_at')
                ->limit(4)
                ->get(),
        ]);
    }

    /**
     * The published post immediately either side of $post in reading order.
     *
     * `published_at` alone is not a total order: the importer stores date-only
     * values, so every post published on the same day shares a timestamp. With
     * a bare `<` / `>` comparison those posts are mutually invisible — each is
     * excluded from the other's query — and prev/next silently steps over them.
     *
     * So the order is (published_at, id): ties are broken on the primary key,
     * which is unique, giving every post exactly one predecessor and one
     * successor. The two directions are mirror images of the same comparator,
     * which is what makes next(previous(X)) === X hold.
     *
     * @param  'asc'|'desc'  $direction  'desc' walks backwards for the previous
     *                                   post, 'asc' forwards for the next one.
     */
    private static function neighbour(BlogPost $post, string $direction): ?BlogPost
    {
        // 'desc' looks for the greatest row below $post, so it compares with
        // '<'; 'asc' looks for the least row above, so it compares with '>'.
        $before = $direction === 'desc';
        $operator = $before ? '<' : '>';
        $key = $post->getKeyName();

        return self::published()
            ->where(fn (Builder $query) => $query
                ->where('published_at', $operator, $post->published_at)
                // The tie: same instant, so the primary key decides.
                ->orWhere(fn (Builder $tied) => $tied
                    ->where('published_at', '=', $post->published_at)
                    ->where($key, $operator, $post->getKey())))
            ->orderBy('published_at', $direction)
            ->orderBy($key, $direction)
            ->first();
    }

    /**
     * The one definition of "visible", shared by the post itself, its
     * neighbours and the latest row — so they can never disagree about what is
     * live.
     */
    private static function published(): Builder
    {
        return BlogPost::query()
            ->where('status', BlogPost::STATUS_PUBLISHED)
            ->where('published_at', '<=', now());
    }
}
