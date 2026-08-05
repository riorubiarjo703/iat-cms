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
            'previous' => self::published()
                ->where('published_at', '<', $post->published_at)
                ->orderByDesc('published_at')
                ->first(),
            'next' => self::published()
                ->where('published_at', '>', $post->published_at)
                ->orderBy('published_at')
                ->first(),
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
