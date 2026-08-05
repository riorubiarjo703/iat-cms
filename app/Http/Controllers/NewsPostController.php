<?php

namespace App\Http\Controllers;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Contracts\View\View;

class NewsPostController extends Controller
{
    public function __invoke(string $slug): View
    {
        // Published means published now. A post marked published but dated in
        // the future is scheduled, not live, and must 404 rather than 403 — an
        // unlisted URL should not confirm that a post exists.
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('status', BlogPost::STATUS_PUBLISHED)
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return view('news.show', ['post' => $post]);
    }
}
