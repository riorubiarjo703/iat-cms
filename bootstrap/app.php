<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Narrower than Laravel's default, which answers JSON to anything that
        // asks for it. Kept narrow deliberately: a web page should get a
        // rendered error, not a JSON blob, even if some fetch on it sent an
        // Accept header.
        //
        // "contact" is listed because the enquiry form posts with fetch and
        // needs the field errors back as JSON. Without it, a validation
        // failure came back as a redirect, which fetch follows silently — the
        // browser then read a whole HTML page as the answer and the form
        // appeared to do nothing at all.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'contact'),
        );
    })->create();
