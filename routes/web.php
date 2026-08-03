<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * Pages are matched last, so a real route always wins. The slug pattern
 * excludes anything containing a slash, which keeps the admin panel and any
 * future prefixed routes out of reach of this catch-all.
 */
Route::get('/{slug}', PageController::class)
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('page');
