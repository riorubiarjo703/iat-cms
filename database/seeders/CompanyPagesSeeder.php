<?php

namespace Database\Seeders;

use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Database\Seeder;

/**
 * Content for Our Milestone, Organisation Structure and Awards & Certification,
 * transcribed from the corresponding scbd.com pages.
 *
 * English only, for the same reason as the profile page: the other locales
 * exist on the live site but are not transcribed here, and inventing
 * translations would be worse than falling back.
 *
 * Overwrites each page's blocks, so re-running discards builder edits.
 */
class CompanyPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->milestone();
        $this->organisation();
        $this->awards();
    }

    private function write(string $slug, array $attributes): void
    {
        $page = Page::query()->where('slug', $slug)->first();

        if ($page === null) {
            $this->command?->warn("No page with slug \"{$slug}\" — run NavigationTreeSeeder first.");

            return;
        }

        $page->update($attributes);
        $this->command?->info("{$slug}: ".count($page->refresh()->blocks()).' blocks');
    }

    private function hero(string $id, string $eyebrow, string $heading, ?string $body = null, ?string $image = null): array
    {
        return [
            'id' => $id,
            'type' => Blocks\PageHeroBlock::type(),
            'children' => null,
            'data' => [
                'eyebrow' => ['en' => $eyebrow],
                'heading' => ['en' => $heading],
                'body' => $body ? ['en' => $body] : [],
                'image' => $image,
                'image_caption' => null,
            ],
        ];
    }

    private function milestone(): void
    {
        $entries = [
            ['1987 – 1992', 'Preparation of the SCBD masterplan', 'The starting point of the business journey of the Company as a provider of real estate services and investment under the name of PT Danayasa Arthatama.', 'uploads/pages/milestone/m1987.jpg'],
            ['1992 – 1993', 'SCBD infrastructure development', 'The Government of the Province of DKI Jakarta gives the Company their trust to transform the 45-hectare slums in the heart of the Jakarta Golden Triangle into an integrated and modern commercial area.', 'uploads/pages/milestone/m1992.jpg'],
            ['1995', 'Artha Graha Building', 'The first office building in SCBD.', 'uploads/pages/milestone/m1995.jpg'],
            ['1998', 'Indonesia Stock Exchange Building', 'The Indonesia Stock Exchange Building and Kusuma Chandra Apartment were completed.', 'uploads/pages/milestone/m1998.jpg'],
            ['2004 – 2006', 'SCBD Suites and Capital Residence', 'SCBD Suites and Capital Residence apartments were completed.', 'uploads/pages/milestone/m2004.jpg'],
            ['2007 – 2011', 'One Pacific Place and Equity Tower', 'One Pacific Place — retail, hotels and exclusive apartments — and Equity Tower are completed.', 'uploads/pages/milestone/m2007.jpg'],
            ['2013 – 2017', 'Pacific Century Place', 'A true premium Grade-A office building with Green Mark and LEED Platinum certification for its innovative eco-friendly design. The building is 34 stories tall and equipped with advanced features, including a stunningly vibrant outdoor LED light system.', 'uploads/pages/milestone/m2013.jpg'],
            ['2017 – now', 'Discovery SCBD and District 8', 'Discovery SCBD is a new urban green concept five-star hotel, inspired by and crafted especially for SCBD. In addition, the recently completed District 8 introduces a new collection of towers offering luxurious office, apartment, retail and hospitality destinations that elevate the opulence of the district even further with unique design aesthetics and landscape.', 'uploads/pages/milestone/m2017.jpg'],
        ];

        $this->write('milestone', [
            'title' => ['en' => 'Our Milestone'],
            'seo_title' => ['en' => 'Our Milestone — PT Danayasa Arthatama'],
            'seo_description' => ['en' => 'Four decades of building Jakarta, from the SCBD masterplan in 1987 to District 8 today.'],
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => [
                $this->hero('block_ms_hero', 'Company', "Forty years of\nbuilding Jakarta",
                    'From a masterplan drawn in 1987 to an integrated district of offices, residences, retail and hotels — the milestones that shaped Sudirman Central Business District.'),
                [
                    'id' => 'block_ms_timeline',
                    'type' => Blocks\TimelineBlock::type(),
                    'children' => null,
                    'data' => [
                        'heading' => ['en' => 'Milestones'],
                        'entries' => array_map(fn (array $e): array => [
                            'year' => $e[0], 'title' => $e[1], 'body' => $e[2], 'image' => $e[3],
                        ], $entries),
                    ],
                ],
            ],
        ]);
    }

    private function organisation(): void
    {
        $commissioners = [
            ['Sugianto Kusuma', 'President Commissioner', 'uploads/pages/people/sugianto-kusuma.png'],
            ['Tomy Winata', 'Commissioner', 'uploads/pages/people/tomy-winata.png'],
            ['Hartono Tjahjadi A.', 'Commissioner', 'uploads/pages/people/hartono-tjahjadi.png'],
            ['Ku Siew Kuan', 'Commissioner', 'uploads/pages/people/ku-siew-kuan.png'],
            ['Santoso Gunara', 'Commissioner', 'uploads/pages/people/santoso-gunara.png'],
            ['Kusmanto', 'Commissioner', 'uploads/pages/people/kusmanto.jpg'],
        ];

        $directors = [
            ['Arpin Wiradisastra', 'President Director', 'uploads/pages/people/arpin-wiradisastra.png'],
            ['Samir', 'Director', 'uploads/pages/people/samir.png'],
            ['Ariefin Surjawirawan', 'Director', 'uploads/pages/people/ariefin-surjawirawan.png'],
            ['Renate Purnama Sari', 'Director', 'uploads/pages/people/renate-purnama-sari.png'],
            ['Hendra Kurniawan', 'Director', 'uploads/pages/people/hendra-kurniawan.png'],
            ['Peter Lie', 'Director', 'uploads/pages/people/peter-lie.jpg'],
        ];

        $person = fn (array $p): array => ['name' => $p[0], 'role' => $p[1], 'photo' => $p[2]];

        $this->write('organisation-structure', [
            'title' => ['en' => 'Organisation Structure'],
            'seo_title' => ['en' => 'Organisation Structure — PT Danayasa Arthatama'],
            'seo_description' => ['en' => 'The Board of Commissioners and Board of Directors of PT Danayasa Arthatama.'],
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => [
                $this->hero('block_org_hero', 'Company', 'Organisation structure',
                    'The Board of Commissioners oversees the Company; the Board of Directors runs it. Together they hold PT Danayasa Arthatama to the governance standards a world-class developer is judged by.'),
                [
                    'id' => 'block_org_people',
                    'type' => Blocks\PeopleBlock::type(),
                    'children' => null,
                    'data' => [
                        'heading' => [],
                        'groups' => [
                            ['title' => 'Board of Commissioners', 'people' => array_map($person, $commissioners)],
                            ['title' => 'Board of Directors', 'people' => array_map($person, $directors)],
                            // The corporate secretary's portrait is not among
                            // the assets that downloaded cleanly, so the entry
                            // carries no photo rather than a broken image.
                            ['title' => 'Corporate Secretary', 'people' => [
                                ['name' => 'Vebby Indrajana', 'role' => 'Corporate Secretary', 'photo' => null],
                            ]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function awards(): void
    {
        $items = [
            ['Penghargaan Water Hero Jakarta', '2023', 'uploads/pages/awards/water-hero-2023.jpg'],
            ['Sertifikasi SNI ISO 14001:2015', null, 'uploads/pages/awards/sni-iso-14001.jpg'],
            ['Sertifikasi SNI ISO 9001:2015', null, 'uploads/pages/awards/sni-iso-9001.jpg'],
            ['Sertifikasi ISO 14001:2015', null, 'uploads/pages/awards/iso-14001.jpg'],
            ['Sertifikasi ISO 45001:2018', null, 'uploads/pages/awards/iso-45001.jpg'],
            ['Sertifikasi ISO 9001:2015', null, 'uploads/pages/awards/iso-9001.jpg'],
            ['100 Fastest Growing Company Awards', '2017', 'uploads/pages/awards/infobank-2017.jpg'],
        ];

        $this->write('awards-certification', [
            'title' => ['en' => 'Awards & Certification'],
            'seo_title' => ['en' => 'Awards & Certification — PT Danayasa Arthatama'],
            'seo_description' => ['en' => 'Awards and management-system certifications held by PT Danayasa Arthatama.'],
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => [
                $this->hero('block_aw_hero', 'Company', "Awards &\ncertifications",
                    'Recognition of the Company’s work, and the quality, environmental and occupational health and safety management systems it is certified against.'),
                [
                    'id' => 'block_aw_items',
                    'type' => Blocks\AwardsBlock::type(),
                    'children' => null,
                    'data' => [
                        'heading' => [],
                        'items' => array_map(fn (array $i): array => [
                            'title' => $i[0], 'year' => $i[1], 'image' => $i[2],
                        ], $items),
                    ],
                ],
            ],
        ]);
    }
}
