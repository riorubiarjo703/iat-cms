<?php

namespace App\PageBuilder;

use App\Models\Page;
use App\Models\SiteSetting;

/**
 * Builds the client-side translation payload for a builder page.
 *
 * The homepage switches language without a reload by swapping the innerHTML of
 * every [data-i18n] element from a JSON dictionary. Blocks publish their
 * translatable leaves into that same dictionary, so the switcher keeps working
 * unchanged.
 */
final class BlockTranslations
{
    /** @return array<string, array<string, string>> locale => key => html */
    public static function forPage(Page $page, BlockRegistry $registry): array
    {
        $payload = [];

        foreach (array_keys(SiteSetting::LOCALES) as $locale) {
            $payload[$locale] = self::walk($page->blocks(), $registry, $locale);
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, string>
     */
    private static function walk(array $blocks, BlockRegistry $registry, string $locale): array
    {
        $bucket = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $class = $type ? $registry->get($type) : null;

            if ($class === null) {
                continue;
            }

            $id = (string) ($block['id'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            foreach ($class::translatableKeys() as $key) {
                // Every declared key is emitted, empty ones included. A block
                // view renders its data-i18n attribute whether or not the field
                // has content, so omitting the key would leave an element the
                // switcher cannot answer for. Empty values are skipped by the
                // switcher itself.
                //
                // Pre-escaped with <br> for line breaks, matching what the
                // switcher assigns straight into innerHTML.
                $bucket[BlockData::i18nKey($id, $key)] = nl2br(e(BlockData::t($data, $key, $locale) ?? ''));
            }

            foreach ($block['children'] ?? [] as $bucketNode) {
                $bucket += self::walk($bucketNode['items'] ?? [], $registry, $locale);
            }
        }

        return $bucket;
    }
}
