<?php

use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsPostController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * Declared before the page catch-all. The catch-all only answers GET, so there
 * is no conflict today, but a POST route defined after it reads as though it
 * might be shadowed.
 *
 * The limit counts every request that reaches the route, including ones that
 * fail validation — so it has to leave room for somebody mistyping their email
 * a few times. A tight limit locks that person out for an hour, which is a far
 * likelier outcome than stopping a determined bot. The honeypot is the actual
 * spam filter; this is only a flood stop.
 */
Route::post('/contact', ContactMessageController::class)
    ->middleware('throttle:15,60')
    ->name('contact.store');

/*
 * Declared before the page catch-all, whose slug pattern excludes anything
 * containing a slash — so a post URL could never reach this controller if the
 * two were the other way round.
 */
Route::get('/news/{slug}', NewsPostController::class)
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('news.show');

/*
 * Pages are matched last, so a real route always wins. The slug pattern
 * excludes anything containing a slash, which keeps the admin panel and any
 * future prefixed routes out of reach of this catch-all.
 */
Route::get('/{slug}', PageController::class)
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('page');
