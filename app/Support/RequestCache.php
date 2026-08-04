<?php

namespace App\Support;

use Closure;
use stdClass;

/**
 * Memoises values for the lifetime of one request.
 *
 * Site settings and the assigned menus are read many times while rendering a
 * page — every block view, the header, the footer band and the translation
 * payload — and each read was a fresh query.
 *
 * Scoped to the container rather than a static property on purpose: the
 * container is rebuilt per request and per test, so nothing leaks between
 * them. A static array would carry one test's settings into the next.
 */
final class RequestCache
{
    private const KEY = 'scbd.request-cache';

    public static function remember(string $key, Closure $resolve): mixed
    {
        $store = self::store();

        if (! property_exists($store, $key)) {
            $store->{$key} = $resolve();
        }

        return $store->{$key};
    }

    /** Drops everything, or everything under a prefix. */
    public static function flush(string $prefix = ''): void
    {
        $store = self::store();

        foreach (array_keys(get_object_vars($store)) as $key) {
            if ($prefix === '' || str_starts_with($key, $prefix)) {
                unset($store->{$key});
            }
        }
    }

    private static function store(): stdClass
    {
        if (! app()->bound(self::KEY)) {
            app()->instance(self::KEY, new stdClass);
        }

        return app(self::KEY);
    }
}
