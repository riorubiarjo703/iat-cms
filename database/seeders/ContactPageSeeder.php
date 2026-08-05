<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Database\Seeder;

/**
 * Content for the Contact Us page.
 *
 * English only, like the other Company pages: inventing Indonesian and Chinese
 * copy would be worse than falling back to English.
 *
 * The address is deliberately absent from this payload. The location block —
 * the same one the District & Facilities page uses — falls back to Site
 * Settings, so the address stays in one place and the footer cannot drift
 * from the contact page.
 *
 * Overwrites the page's blocks, so it is safe to re-run while the design is
 * being iterated but will discard edits made in the builder.
 */
class ContactPageSeeder extends Seeder
{
    private const MAP_QUERY = 'Sudirman+Central+Business+District+Jakarta';

    /**
     * The same keyless Google embed the District & Facilities page uses.
     * The place-query form needs no API key and no billing account, and
     * sharing one URL shape means both maps look and behave alike.
     */
    private const MAP_EMBED = 'https://www.google.com/maps?q='.self::MAP_QUERY.'&output=embed';

    public function run(): void
    {
        $page = Page::query()->where('slug', 'contact-us')->first()
            ?? Page::query()->where('slug', 'contact')->first();

        if ($page === null) {
            $this->command?->warn('No page with slug "contact-us" — run NavigationTreeSeeder first.');

            return;
        }

        $page->update([
            'title' => ['en' => 'Contact Us'],
            'seo_title' => ['en' => 'Contact Us — PT Danayasa Arthatama'],
            'seo_description' => ['en' => 'Talk to the team behind Sudirman Central Business District about leasing, facilities, partnerships and press.'],
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => $this->blocks(),
        ]);

        $this->pointNavigationAtPage($page);
    }

    /** @return array<int, array<string, mixed>> */
    private function blocks(): array
    {
        return [
            [
                'id' => 'block_contact_hero',
                'type' => Blocks\PageHeroBlock::type(),
                'children' => null,
                'data' => [
                    'eyebrow' => ['en' => 'Contact'],
                    'heading' => ['en' => "Start a\nconversation"],
                    'body' => ['en' => implode("\n\n", [
                        'Whether you are looking for space in the district, managing an existing tenancy, or writing about it, there is someone here who can help.',
                        'Tell us what you need and we will point you at the right desk.',
                    ])],
                    'image' => 'uploads/pages/contact-hero.jpg',
                    'image_caption' => 'Jl. Jend. Sudirman, Jakarta',
                ],
            ],
            [
                'id' => 'block_contact_marquee',
                'type' => Blocks\MarqueeBlock::type(),
                'children' => null,
                'data' => ['text' => ['en' => 'Get in touch']],
            ],
            [
                'id' => 'block_contact_location',
                'type' => Blocks\LocationBlock::type(),
                'children' => null,
                'data' => [
                    'eyebrow' => ['en' => 'Visit'],
                    'heading' => ['en' => 'Where to find us'],
                    'address_heading' => ['en' => 'Address'],
                    // Empty, so the block falls back to Site Settings. Typing
                    // the address here would give the site a second copy to
                    // keep in step with the footer.
                    'address' => [],
                    'contact_heading' => ['en' => 'Contact'],
                    'contact' => ['en' => "Tel: +62 (21) 515-2390\nFax: +62 (21) 515-2391"],
                    'access_heading' => ['en' => 'Getting here'],
                    'access' => [
                        ['label' => 'Metro', 'text' => 'Sudirman station, with a direct connection into the district.'],
                        ['label' => 'Car', 'text' => 'On the Jl. Jendral Sudirman arterial corridor.'],
                        ['label' => 'Parking', 'text' => 'Over 3,200 structured spaces across the district.'],
                        ['label' => 'Shuttle', 'text' => 'A free shuttle bus circulates between the district nodes.'],
                    ],
                    'map_embed_url' => self::MAP_EMBED,
                    'facts' => [
                        ['label' => 'Distance from airport', 'value' => '22 km'],
                        ['label' => 'Central Jakarta', 'value' => 'Prime'],
                    ],
                ],
            ],
            [
                'id' => 'block_contact_form',
                'type' => Blocks\ContactFormBlock::type(),
                'children' => null,
                'data' => [
                    'heading' => ['en' => "How can\nwe help?"],
                    'intro' => ['en' => 'Send us the details and we will come back to you. Marked fields are required.'],
                    'submit' => ['en' => 'Send enquiry'],
                    'success' => ['en' => 'Thank you — your enquiry has reached us. We will be in touch shortly.'],
                    // Generic starting categories, editable in the builder.
                    'subjects' => [
                        ['label' => 'Leasing enquiry'],
                        ['label' => 'Existing tenancy'],
                        ['label' => 'Facilities & building management'],
                        ['label' => 'Partnership'],
                        ['label' => 'Media & press'],
                        ['label' => 'Something else'],
                    ],
                ],
            ],
        ];
    }

    /**
     * The navigation item still pointed at the homepage's #contact anchor,
     * which was correct while there was no page to send anyone to. Now there
     * is one, so the link goes to it.
     */
    private function pointNavigationAtPage(Page $page): void
    {
        $item = MenuItem::query()->get()
            ->first(fn (MenuItem $i): bool => str_contains(strtolower((string) $i->t('label', 'en')), 'contact'));

        $item?->update([
            'type' => MenuItem::TYPE_PAGE,
            'linkable_type' => Page::class,
            'linkable_id' => $page->getKey(),
            'url' => null,
        ]);
    }
}
