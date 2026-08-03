<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __invoke(string $slug): View
    {
        // Unpublished and scheduled pages 404 rather than 403: an unlisted URL
        // should not confirm that a page exists.
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return view('page', ['page' => $page]);
    }
}
