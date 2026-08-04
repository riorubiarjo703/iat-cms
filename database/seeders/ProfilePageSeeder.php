<?php

namespace Database\Seeders;

use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Database\Seeder;

/**
 * Content for the Company Profile page, transcribed from scbd.com/menu/page/profile.
 *
 * English only: the Indonesian and Chinese versions of this copy exist on the
 * live site but are not transcribed here, and inventing translations would be
 * worse than leaving the fallback to English.
 *
 * Overwrites the page's blocks, so it is safe to re-run while the design is
 * being iterated but will discard edits made in the builder.
 */
class ProfilePageSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::query()->where('slug', 'profile')->first();

        if ($page === null) {
            $this->command?->warn('No page with slug "profile" — run NavigationTreeSeeder first.');

            return;
        }

        $page->update([
            'title' => ['en' => 'Company Profile'],
            'seo_title' => ['en' => 'Company Profile — PT Danayasa Arthatama'],
            'seo_description' => ['en' => 'PT Danayasa Arthatama develops and manages Sudirman Central Business District, a 45-hectare integrated commercial area in Jakarta.'],
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => [
                [
                    'id' => 'block_profile_hero',
                    'type' => Blocks\PageHeroBlock::type(),
                    'children' => null,
                    'data' => [
                        'eyebrow' => ['en' => 'Company'],
                        'heading' => ['en' => "The world-class\nproperty developer"],
                        'body' => ['en' => implode("\n\n", [
                            'Armed with three decades of experience, PT Danayasa Arthatama (Company) continues to grow driven by a spirit of sustainability and innovation reflected in its operational activities and the values upheld by the Company.',
                            'The Company implements a business model that is grounded in synergy and diversification by focusing its business on the property segment (real estate and hotels), as well as telecommunication services. Through the development and management of the integrated commercial area Sudirman Central Business District (SCBD), the Company has proved its quality as a leading world-class property developer and manager in Indonesia.',
                            'Covering an area of approximately ±50 hectares in Jakarta’s Golden Triangle, the district has developed into a premium business center featuring office buildings, exclusive residences, modern shopping centers, and five-star hotels, supported by integrated facilities and infrastructure.',
                            'The Company’s business activities include property development and the management of integrated commercial areas along with supporting facilities, the provision of infrastructure and utilities, and the delivery of general services, excluding legal and tax services. The Company also continues to enhance the implementation of good corporate governance as well as quality and environmental management systems to achieve effective and efficient operational activities.',
                        ])],
                        'image' => 'uploads/pages/profile-hero.jpg',
                        'image_caption' => 'Sudirman Central Business District',
                    ],
                ],
                [
                    'id' => 'block_profile_vision',
                    'type' => Blocks\VisionMissionBlock::type(),
                    'children' => null,
                    'data' => [
                        'vision_label' => ['en' => 'Vision'],
                        'vision' => ['en' => 'To be the leading world-class property developer and manager.'],
                        'mission_label' => ['en' => 'Mission'],
                        'mission' => ['en' => [
                            ['text' => 'To enhance the Company’s performance through strategic planning.'],
                            ['text' => 'To attain synergy with the principles of responsible and mutually beneficial business practices.'],
                            ['text' => 'To provide best service to stakeholders.'],
                            ['text' => 'To improve the competence and welfare of the Company’s human resources in order to achieve its development targets.'],
                            ['text' => 'To utilize technological advancements to innovatively create environmentally friendly premium products.'],
                        ]],
                        'vision_image' => 'uploads/pages/profile-vision.jpg',
                        'mission_image' => 'uploads/pages/profile-mission.png',
                    ],
                ],
                [
                    'id' => 'block_profile_values',
                    'type' => Blocks\ValuesBlock::type(),
                    'children' => null,
                    'data' => [
                        'heading' => ['en' => 'Corporate culture'],
                        'acronym' => ['en' => 'SUSTAIN'],
                        'values' => ['en' => [
                            ['name' => 'Smart', 'description' => 'Working based on competency in an effective, adaptive and measurable manner.'],
                            ['name' => 'Unity', 'description' => 'Fostering harmony and the spirit of collaboration in achieving the Company’s objectives.'],
                            ['name' => 'Safety', 'description' => 'Guided by procedures in maintaining occupational safety, health and productivity.'],
                            ['name' => 'Transformation', 'description' => 'Improvement-oriented through visionary strategies.'],
                            ['name' => 'Active', 'description' => 'Active engagement and hard work in the development of the Company and the community.'],
                            ['name' => 'Innovative', 'description' => 'Optimizing creativity in generating innovation.'],
                            ['name' => 'Noble', 'description' => 'Upholding integrity, morals and traditional values in every aspect of work.'],
                        ]],
                    ],
                ],
            ],
        ]);

        $this->command?->info('Profile page content written ('.count($page->refresh()->blocks()).' blocks).');
    }
}
