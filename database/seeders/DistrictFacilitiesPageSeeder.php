<?php

namespace Database\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Database\Seeder;

/**
 * The District Facilities page, from the SCBD District Guide design: the hero
 * the page already carried, then Places of Interest, Location & Access,
 * District Facilities and a closing call to action.
 *
 * The two list sections read the District place and Facility records the
 * homepage also uses, so this seeder fills in the columns those sections need
 * and leaves each record's existing title, caption, body and image alone —
 * overwriting them would rewrite the homepage as a side effect.
 *
 * English only, as with the company pages: the other locales exist on the live
 * site but are not transcribed here, and inventing translations would be worse
 * than falling back.
 *
 * Overwrites the page's blocks, so re-running discards builder edits.
 */
class DistrictFacilitiesPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->places();
        $this->facilities();
        $this->page();
    }

    /**
     * Matched on image rather than title: the image filename is what ties a
     * record to a row of the design, and the titles are the site's own wording.
     */
    private function places(): void
    {
        $copy = [
            'uploads/district/offices.jpg' => [
                'body' => 'Grade A office towers hosting multinational corporations and local enterprises. Ground-floor retail and food courts serve the working population and visitors throughout the day.',
                'tags' => 'Artha Graha, Plaza Sudirman, Landmark Tower',
                'stat_label' => 'Visitors per day',
                'stat_value' => '18K+',
            ],
            'uploads/district/hospitality.jpg' => [
                'body' => 'Five-star hotels offering world-class accommodation. Fine dining restaurants, casual cafés and bars create a vibrant hospitality scene for business travellers and guests.',
                'tags' => 'Bidakara, Restaurants, Lounges',
                'stat_label' => 'Hotel rooms',
                'stat_value' => '650+',
            ],
            'uploads/district/publicrealm.png' => [
                'body' => 'Art galleries, performance spaces and public forums host exhibitions, concerts and community events year-round. The open plaza becomes a gathering place for cultural celebration.',
                'tags' => 'Galleries, Events, Public Space',
                'stat_label' => 'Annual events',
                'stat_value' => '40+',
            ],
        ];

        $this->fill(DistrictPlace::query()->get(), $copy, 'District places');
    }

    private function facilities(): void
    {
        $copy = [
            'uploads/facilities/fireservice.jpg' => [
                'eyebrow' => '24/7 Operations',
                'stat_label' => 'Team strength',
                'stat_value' => '32 personnel',
            ],
            'uploads/facilities/clinic.jpg' => [
                'eyebrow' => 'Health Services',
                'stat_label' => 'Patients annually',
                'stat_value' => '4,500+',
            ],
            'uploads/facilities/security.png' => [
                'eyebrow' => 'Full Coverage',
                'stat_label' => 'Security personnel',
                'stat_value' => '180+ staff',
            ],
            'uploads/facilities/transport.png' => [
                'eyebrow' => 'Circulation',
                'stat_label' => 'Parking spaces',
                'stat_value' => '3,200+',
            ],
        ];

        $this->fill(Facility::query()->get(), $copy, 'Facilities');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $records
     * @param  array<string, array<string, string>>  $copy
     */
    private function fill($records, array $copy, string $label): void
    {
        $filled = 0;

        foreach ($records as $record) {
            $fields = $copy[$record->image] ?? null;

            if ($fields === null) {
                continue;
            }

            $attributes = [];

            foreach ($fields as $key => $value) {
                // Translatable columns are locale maps; stat_value is a bare
                // figure that reads the same in every language.
                $attributes[$key] = in_array($key, $record::TRANSLATABLE, true)
                    ? ['en' => $value]
                    : $value;
            }

            $record->update($attributes);
            $filled++;
        }

        $this->command?->info("{$label}: {$filled} of ".$records->count().' records filled in.');
    }

    private function page(): void
    {
        $page = Page::query()->where('slug', 'district-facilities')->first();

        if ($page === null) {
            $this->command?->warn('No page with slug "district-facilities" — run NavigationTreeSeeder first.');

            return;
        }

        // The hero was built in the page builder and carries an uploaded
        // image, so it is carried over as it stands rather than re-specified
        // here — re-specifying it would drop whichever photograph an editor
        // has since chosen.
        $hero = collect($page->blocks())
            ->first(fn (array $block): bool => ($block['type'] ?? null) === Blocks\PageHeroBlock::type())
            ?? [
                'id' => 'block_df_hero',
                'type' => Blocks\PageHeroBlock::type(),
                'children' => null,
                'data' => [
                    'eyebrow' => ['en' => 'District'],
                    'heading' => ['en' => 'Places, Location, Facilities'],
                    'body' => ['en' => 'Everything the district has to offer — from world-class retail and hospitality to the infrastructure that keeps forty-five hectares running seamlessly, day and night.'],
                    'image' => null,
                    'image_caption' => null,
                ],
            ];

        $page->update([
            'title' => ['en' => 'District & Facilities'],
            'seo_title' => ['en' => 'District & Facilities — Sudirman Central Business District'],
            'seo_description' => ['en' => 'Places of interest, location and access, and the facilities that keep forty-five hectares of Jakarta running.'],
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => [
                $hero,
                [
                    'id' => 'block_df_places',
                    'type' => Blocks\PlacesBlock::type(),
                    'children' => null,
                    'data' => [
                        'eyebrow' => ['en' => 'District'],
                        'heading' => ['en' => 'Places of interest'],
                    ],
                ],
                [
                    'id' => 'block_df_location',
                    'type' => Blocks\LocationBlock::type(),
                    'children' => null,
                    'data' => [
                        'eyebrow' => ['en' => 'Navigate'],
                        'heading' => ['en' => 'Location & access'],
                        'address_heading' => ['en' => 'Address'],
                        // Blank: the address comes from Site settings, so it is
                        // corrected in one place.
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
                        // A keyless embed: the place query form of the Maps
                        // embed needs no API key and no billing account.
                        'map_embed_url' => 'https://www.google.com/maps?q=Sudirman+Central+Business+District,+Jl.+Jend.+Sudirman,+Jakarta+12190&output=embed',
                        'facts' => [
                            ['label' => 'Distance from airport', 'value' => '22 km'],
                            ['label' => 'Central Jakarta', 'value' => 'Prime'],
                        ],
                    ],
                ],
                [
                    'id' => 'block_df_operations',
                    'type' => Blocks\OperationsBlock::type(),
                    'children' => null,
                    'data' => [
                        'eyebrow' => ['en' => 'Operations'],
                        'heading' => ['en' => 'District facilities'],
                    ],
                ],
                [
                    'id' => 'block_df_cta',
                    'type' => Blocks\CtaBlock::type(),
                    'children' => null,
                    'data' => [
                        'heading' => ['en' => 'Ready to explore?'],
                        'body' => ['en' => 'Visit SCBD and experience the integration of work, culture and community in Jakarta’s premier business district.'],
                        'button_label' => ['en' => 'Get in touch'],
                        'button_url' => '/contact-us',
                    ],
                ],
            ],
        ]);

        $this->command?->info('district-facilities: '.count($page->refresh()->blocks()).' blocks');
    }
}
