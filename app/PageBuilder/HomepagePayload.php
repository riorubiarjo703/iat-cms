<?php

namespace App\PageBuilder;

use App\Models\HomepageContent;
use App\PageBuilder\Blocks;

/**
 * Builds a block payload from the hand-built homepage's content record.
 *
 * This is the bridge that makes the homepage editable: the copy that lived in
 * HomepageContent columns becomes block data, after which the page is edited
 * like any other and HomepageContent can retire.
 */
final class HomepagePayload
{
    /** @return array<int, array<string, mixed>> */
    public static function fromContent(HomepageContent $content): array
    {
        $t = fn (string $column): array => $content->translations($column);

        return [
            self::block(Blocks\HeroBlock::type(), 'hero', [
                'heading' => $t('hero_line'),
                'subheading' => $t('hero_sub'),
                'image' => $content->hero_image,
                'location_tag' => null,
            ]),
            self::block(Blocks\MarqueeBlock::type(), 'marquee', [
                'text' => $t('marquee_text'),
            ]),
            self::block(Blocks\AboutBlock::type(), 'about', [
                // The eyebrow and badge were hardcoded in the old partial;
                // carried across verbatim so nothing on the page changes.
                'eyebrow' => ['en' => 'Who we are'],
                'heading' => $t('about_heading'),
                'body' => $t('about_body'),
                'cta_label' => $t('about_cta_label'),
                'cta_url' => $content->about_cta_url,
                'badge_label' => ['en' => 'Certified'],
                'badge_text' => ['en' => "ISO & SMK3\naccredited operations"],
                'image' => $content->about_image,
                'show_stats' => true,
            ]),
            self::block(Blocks\DistrictBlock::type(), 'district', [
                'eyebrow' => ['en' => 'The district'],
                'heading' => $t('district_heading'),
                'body' => $t('district_body'),
                'location_label' => ['en' => 'Location'],
                'directions_label' => ['en' => 'Get directions'],
                'directions_url' => '#contact',
            ]),
            self::block(Blocks\FacilitiesBlock::type(), 'facilities', [
                'eyebrow' => ['en' => 'District facilities'],
                'heading' => $t('facilities_heading'),
                'body' => $t('facilities_body'),
            ]),
            self::block(Blocks\NewsBlock::type(), 'news', [
                'eyebrow' => ['en' => 'Newsroom & CSSR'],
                'heading' => $t('news_heading'),
                'cta_label' => $t('news_cta_label'),
                'empty_text' => ['en' => 'More from the district, coming soon'],
                'limit' => 3,
            ]),
            self::block(Blocks\ContactHeadingBlock::type(), 'contact', [
                'heading' => $t('contact_heading'),
            ]),
        ];
    }

    /**
     * Ids are stable and derived from the section name rather than random, so
     * re-running this produces the same payload — and the i18n keys a rendered
     * page publishes do not change underneath a cached translation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function block(string $type, string $id, array $data): array
    {
        return ['id' => "block_{$id}", 'type' => $type, 'data' => $data, 'children' => null];
    }
}
