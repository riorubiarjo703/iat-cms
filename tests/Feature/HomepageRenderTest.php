<?php

namespace Tests\Feature;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\SiteSetting;
use App\Models\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class HomepageRenderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    private function seedMinimum(): void
    {
        HomepageContent::singleton()->update([
            'brand_sub' => ['en' => 'Danayasa Arthatama'],
            'hero_line' => ['en' => "A district\nthat never\nclocks out"],
            'hero_sub' => ['en' => 'Forty-five hectares.'],
            'district_heading' => ['en' => "Everything inside\none walk"],
            'facilities_heading' => ['en' => "Services that\nrun underneath"],
            'news_heading' => ['en' => "Latest from\nthe district"],
            'contact_heading' => ['en' => "Take an address\nin the district"],
            'marquee_text' => ['en' => 'Offices — Hotels'],
            'contact_address' => "Jl. Jenderal\nSudirman",
        ]);
        $this->seedHeaderMenu(
            links: [['label' => ['en' => 'Company'], 'url' => '#about']],
            cta: ['label' => ['en' => 'Leasing enquiry'], 'url' => '#contact'],
        );
        DistrictPlace::create(['title' => ['en' => 'The towers'], 'caption' => ['en' => 'Grade A office'], 'sort' => 1]);
        Facility::create(['title' => ['en' => 'District clinic'], 'body' => ['en' => 'On-site care.'], 'sort' => 1]);
        Stat::create(['label' => ['en' => 'Hectares'], 'value' => 45, 'sort' => 1]);
    }

    public function test_all_animation_hooks_are_present(): void
    {
        $this->seedMinimum();

        $response = $this->get('/');

        foreach ([
            'data-loader', 'data-loader-num', 'data-loader-bar',
            'data-header', 'data-navlink', 'data-lang',
            'data-split', 'data-parallax-wrap', 'data-parallax',
            'data-marquee', 'data-fade', 'data-reveal', 'data-count',
            'data-horizontal-track', 'data-stack', 'data-card',
            'data-news', 'data-cursor', 'data-cursor-ring', 'data-magnetic',
        ] as $hook) {
            $response->assertSee($hook, false);
        }
    }

    /**
     * test_all_animation_hooks_are_present only proves each hook string
     * appears somewhere in the document, so a hook copy-pasted into the
     * wrong section would still pass it. This test slices the response
     * around each section's id and asserts every hook shows up inside its
     * own section — and, for the section-scoped hooks, nowhere else.
     */
    public function test_animation_hooks_are_scoped_to_their_sections(): void
    {
        $this->seedMinimum();

        $html = $this->get('/')->getContent();

        $slice = function (string $fromMarker, ?string $toMarker) use ($html): string {
            $start = strpos($html, $fromMarker);
            $this->assertIsInt($start, "Marker [{$fromMarker}] not found in response.");

            if ($toMarker === null) {
                return substr($html, $start);
            }

            $end = strpos($html, $toMarker, $start);
            $this->assertIsInt($end, "Marker [{$toMarker}] not found after [{$fromMarker}].");

            return substr($html, $start, $end - $start);
        };

        $chrome = $slice('<body', 'id="top"');
        $hero = $slice('id="top"', 'id="about"');
        $about = $slice('id="about"', 'id="district"');
        $district = $slice('id="district"', 'id="facilities"');
        $facilities = $slice('id="facilities"', 'id="news"');
        $news = $slice('id="news"', 'id="contact"');

        $this->assertStringContainsString('data-loader', $chrome);
        $this->assertStringContainsString('data-loader-num', $chrome);
        $this->assertStringContainsString('data-loader-bar', $chrome);
        $this->assertStringContainsString('data-header', $chrome);
        $this->assertStringContainsString('data-navlink', $chrome);
        $this->assertStringContainsString('data-lang', $chrome);
        $this->assertStringContainsString('data-cursor', $chrome);
        $this->assertStringContainsString('data-cursor-ring', $chrome);
        $this->assertStringContainsString('data-magnetic', $chrome);

        $this->assertStringContainsString('data-split', $hero);
        $this->assertStringContainsString('data-parallax-wrap', $hero);
        $this->assertStringContainsString('data-parallax', $hero);

        $this->assertStringContainsString('data-fade', $about);
        $this->assertStringContainsString('data-count', $about);
        $this->assertStringContainsString('data-reveal', $about);
        $this->assertStringNotContainsString('data-card', $about);
        $this->assertStringNotContainsString('data-stack', $about);

        $this->assertStringContainsString('data-horizontal-track', $district);
        $this->assertStringNotContainsString('data-card', $district);
        $this->assertStringNotContainsString('data-count', $district);

        $this->assertStringContainsString('data-stack', $facilities);
        $this->assertStringContainsString('data-card', $facilities);
        $this->assertStringNotContainsString('data-horizontal-track', $facilities);
        $this->assertStringNotContainsString('data-count', $facilities);

        $this->assertStringContainsString('data-news', $news);
        $this->assertStringNotContainsString('data-card', $news);
        $this->assertStringNotContainsString('data-count', $news);
    }

    public function test_all_sections_are_present(): void
    {
        $this->seedMinimum();

        $response = $this->get('/');

        foreach (['id="top"', 'id="about"', 'id="district"', 'id="facilities"', 'id="news"', 'id="contact"'] as $section) {
            $response->assertSee($section, false);
        }
    }

    public function test_headings_emit_br_separated_lines_for_the_char_split(): void
    {
        $this->seedMinimum();

        $this->get('/')->assertSee('A district<br>that never<br>clocks out', false);
    }

    public function test_the_i18n_payload_is_embedded(): void
    {
        $this->seedMinimum();

        $html = $this->get('/')
            ->assertSee('id="scbd-i18n"', false)
            ->assertSee('heroline', false)
            ->getContent();

        // The presence checks above would pass on malformed JSON or on the
        // literal word "heroline" appearing anywhere unrelated. Task 17's
        // language switcher JSON.parse()s this element, so decode it for
        // real and verify the shape it depends on: exactly the three
        // locales, each carrying a non-empty "heroline".
        $matched = preg_match(
            '#<script type="application/json" id="scbd-i18n">(.*?)</script>#s',
            $html,
            $groups
        );
        $this->assertSame(1, $matched, 'Could not locate the #scbd-i18n script element in the response.');

        $payload = json_decode($groups[1], true);
        $this->assertNotNull($payload, 'The #scbd-i18n payload is not valid JSON: '.json_last_error_msg());
        $this->assertIsArray($payload);

        $this->assertSame(['en', 'id', 'cn'], array_keys($payload), 'The payload must carry exactly the en/id/cn locales, in order.');

        foreach (['en', 'id', 'cn'] as $locale) {
            $this->assertArrayHasKey('heroline', $payload[$locale], "Locale [{$locale}] is missing the heroline key.");
            $this->assertNotSame('', $payload[$locale]['heroline'], "Locale [{$locale}]'s heroline is empty.");
        }
    }

    public function test_nav_links_are_numbered_for_the_switcher(): void
    {
        $this->seedMinimum();

        $this->get('/')
            ->assertSee('data-i18n="nav1"', false)
            ->assertSee('data-i18n="cta"', false);
    }

    public function test_empty_sections_are_skipped(): void
    {
        HomepageContent::singleton();

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee('data-horizontal-track', false)
            ->assertDontSee('data-stack', false);
    }

    public function test_it_renders_published_posts_in_the_news_section(): void
    {
        $this->seedMinimum();
        BlogPost::create([
            'title' => 'Eco Enzyme as part of household waste management',
            'slug' => 'eco-enzyme',
            'content' => 'x',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/')->assertSee('Eco Enzyme as part of household waste management');
    }

    public function test_a_missing_image_does_not_emit_an_empty_src(): void
    {
        $this->seedMinimum();

        $this->get('/')->assertDontSee('src=""', false);
    }

    /**
     * App::setLocale() is never called, so any ->t() read without an
     * explicit locale (facility/district/stat copy, nav labels) falls back
     * to app()->getLocale() == APP_LOCALE (en) regardless of what's
     * configured here — producing Indonesian headings above English body
     * copy. Facility/district/stat text is a clean probe because, unlike
     * the six headings and nav labels, it never also appears in the
     * #scbd-i18n JSON payload (HomepageData::I18N_MAP has no entries for
     * it), so a passing assertDontSee can't be a false negative caused by
     * the embedded switcher data.
     */
    public function test_body_copy_resolves_in_the_configured_default_locale(): void
    {
        $this->seedMinimum();
        SiteSetting::singleton()->update(['default_locale' => 'id']);

        // Add Indonesian translations onto the records seedMinimum() already
        // created, keeping their English text so a leaked app()->getLocale()
        // == 'en' fallback would still render something recognisable and
        // wrong, rather than silently falling back to the same value.
        DistrictPlace::query()->first()->update([
            'title' => ['en' => 'The towers', 'id' => 'Menara'],
            'caption' => ['en' => 'Grade A office', 'id' => 'Kantor Kelas A'],
        ]);
        Facility::query()->first()->update([
            'title' => ['en' => 'District clinic', 'id' => 'Klinik distrik'],
            'body' => ['en' => 'On-site care.', 'id' => 'Perawatan di tempat.'],
        ]);
        Stat::query()->first()->update([
            'label' => ['en' => 'Hectares', 'id' => 'Hektar'],
        ]);

        $html = $this->get('/')->assertSee('lang="id"', false)->getContent();

        $this->assertStringContainsString('Klinik distrik', $html);
        $this->assertStringContainsString('Kantor Kelas A', $html);
        $this->assertStringContainsString('Hektar', $html);
        $this->assertStringNotContainsString('District clinic', $html);
        $this->assertStringNotContainsString('Grade A office', $html);
        $this->assertStringNotContainsString('Hectares', $html);
    }

    /**
     * default_locale is stored free-form (Fix 2's SiteSettingsPage ->in()
     * rule blocks new writes through the admin form, but existing rows —
     * or ones written before that guard existed — can still hold an
     * invalid value). HomeController must fall back to English rather than
     * crash. This previously 500'd: the fallback branch read
     * `HasTranslatableFields::FALLBACK_LOCALE`, and PHP 8.4 forbids
     * accessing a trait constant directly, so the one path meant to
     * *handle* an invalid locale threw instead.
     */
    public function test_an_invalid_default_locale_falls_back_to_english_instead_of_crashing(): void
    {
        $this->seedMinimum();
        SiteSetting::singleton()->update(['default_locale' => 'zz']);

        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertSee('A district<br>that never<br>clocks out', false);
    }
}
