<?php

namespace App\PageBuilder;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;

/**
 * The client-side translation dictionary for a page.
 *
 * Two sources merge into one payload: the header chrome (brand subtitle, nav
 * labels, the call-to-action) and the page's own blocks. The switcher reads a
 * single flat map per locale, so chrome that lives outside the payload simply
 * would not translate — which is what would happen if only blocks published.
 */
final class SiteTranslations
{
    /** @return array<string, array<string, string>> */
    public static function forPage(Page $page, BlockRegistry $registry): array
    {
        $blocks = BlockTranslations::forPage($page, $registry);
        $payload = [];

        foreach (array_keys(SiteSetting::LOCALES) as $locale) {
            $payload[$locale] = self::chrome($locale) + ($blocks[$locale] ?? []);
        }

        return $payload;
    }

    /** @return array<string, string> */
    public static function chrome(string $locale): array
    {
        $settings = SiteSetting::singleton();
        $bucket = [];

        // Emitted even when empty. The header always renders the element, so
        // omitting the key would leave a data-i18n attribute the dictionary
        // cannot answer; the switcher skips empty values anyway.
        $bucket['brandsub'] = self::html($settings->t('brand_subtitle', $locale) ?? '');

        // The whole tree, not just the top level: a dropdown entry rendered
        // with a key the dictionary lacks would never translate. Keys come
        // from MenuRenderer so the markup and this payload share one source.
        foreach (self::flatten(MenuRenderer::withKeys()) as $key => $item) {
            $bucket[$key] = self::html($item->t('label', $locale) ?? '');
        }

        $cta = MenuRenderer::cta(MenuLocations::HEADER);

        if ($cta !== null) {
            $bucket['cta'] = self::html($cta->t('label', $locale) ?? '');
        }

        return $bucket;
    }

    /**
     * @param  array<int, array{item: \App\Models\MenuItem, key: string, children: array<int, mixed>}>  $nodes
     * @return array<string, \App\Models\MenuItem>
     */
    private static function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[$node['key']] = $node['item'];
            $flat += self::flatten($node['children']);
        }

        return $flat;
    }

    /** Pre-escaped with <br> for line breaks, which is what the switcher
     *  assigns straight into innerHTML. */
    private static function html(string $value): string
    {
        return nl2br(e($value));
    }
}
