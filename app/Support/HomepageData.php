<?php

namespace App\Support;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\Stat;
use Illuminate\Support\Collection;

/**
 * Everything the homepage needs, assembled once.
 *
 * Blade performs no queries, which keeps page assembly a single testable step.
 */
final readonly class HomepageData
{
    /**
     * The reference markup's `data-i18n` keys mapped to HomepageContent columns.
     * Nav keys (`nav1`..`navN`) and `cta` come from PublicMenuItem instead.
     *
     * @var array<string, string>
     */
    public const I18N_MAP = [
        'brandsub' => 'brand_sub',
        'heroline' => 'hero_line',
        'herosub' => 'hero_sub',
        'abouth' => 'about_heading',
        'aboutp' => 'about_body',
        'aboutcta' => 'about_cta_label',
        'disth' => 'district_heading',
        'distp' => 'district_body',
        'fach' => 'facilities_heading',
        'facp' => 'facilities_body',
        'newsh' => 'news_heading',
        'newscta' => 'news_cta_label',
        'contacth' => 'contact_heading',
        'marquee' => 'marquee_text',
    ];

    /**
     * @param  Collection<int, PublicMenuItem>  $menu
     * @param  Collection<int, DistrictPlace>  $places
     * @param  Collection<int, Facility>  $facilities
     * @param  Collection<int, Stat>  $stats
     * @param  Collection<int, BlogPost>  $posts
     * @param  array<string, array<string, string>>  $i18n
     */
    public function __construct(
        public HomepageContent $content,
        public SiteSetting $settings,
        public Collection $menu,
        public ?PublicMenuItem $cta,
        public Collection $places,
        public Collection $facilities,
        public Collection $stats,
        public Collection $posts,
        public array $i18n,
    ) {}

    public static function build(): self
    {
        $content = HomepageContent::singleton();
        $menu = PublicMenuItem::query()->links()->get();
        $cta = PublicMenuItem::query()->cta()->first();

        return new self(
            content: $content,
            settings: SiteSetting::singleton(),
            menu: $menu,
            cta: $cta,
            places: DistrictPlace::query()->active()->ordered()->get(),
            facilities: Facility::query()->active()->ordered()->get(),
            stats: Stat::query()->ordered()->get(),
            posts: BlogPost::query()
                ->where('status', BlogPost::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
            i18n: self::i18nPayload($content, $menu, $cta),
        );
    }

    /**
     * @param  Collection<int, PublicMenuItem>  $menu
     * @return array<string, array<string, string>>
     */
    private static function i18nPayload(HomepageContent $content, Collection $menu, ?PublicMenuItem $cta): array
    {
        $payload = [];

        foreach (array_keys(SiteSetting::LOCALES) as $locale) {
            $bucket = [];

            foreach (self::I18N_MAP as $key => $column) {
                $bucket[$key] = self::html($content->t($column, $locale));
            }

            foreach ($menu->values() as $index => $item) {
                $bucket['nav'.($index + 1)] = self::html($item->t('label', $locale));
            }

            if ($cta !== null) {
                $bucket['cta'] = self::html($cta->t('label', $locale));
            }

            $payload[$locale] = $bucket;
        }

        return $payload;
    }

    /**
     * Escape first, then turn newlines into the `<br>` tags the char-split
     * animation splits headings on.
     */
    private static function html(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace(["\r\n", "\n", "\r"], '<br>', e($value));
    }
}
