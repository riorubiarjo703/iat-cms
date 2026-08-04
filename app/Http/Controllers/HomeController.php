<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // The homepage is an ordinary page carrying is_homepage. There is no
        // hand-built fallback any more, so no page means the site has not been
        // set up — a 404 says that plainly rather than rendering a blank shell.
        $page = Page::homepage() ?? abort(404);

        App::setLocale($this->resolvedLocale());

        return view('page', ['page' => $page]);
    }

    /**
     * The stored locale, or English when it is not one we know about. An
     * invalid value in the column would otherwise blank every heading.
     */
    private function resolvedLocale(): string
    {
        $stored = SiteSetting::singleton()->default_locale;

        return array_key_exists($stored, SiteSetting::LOCALES)
            ? $stored
            : SiteSetting::FALLBACK_LOCALE;
    }
}
