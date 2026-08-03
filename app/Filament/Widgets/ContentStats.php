<?php

namespace App\Filament\Widgets;

use App\Support\ContentHealth;
use Filament\Widgets\Widget;

class ContentStats extends Widget
{
    protected string $view = 'filament.widgets.content-stats';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;


    /**
     * Rendered with the page rather than lazily. These are a few COUNT queries
     * on a company-profile-sized dataset, so the extra round trip buys nothing
     * — and a lazy widget's render failure never reaches the initial response,
     * which is how a broken dashboard passed its tests once already. If
     * coverage ever gets slow, cache it rather than hiding it behind a spinner.
     */
    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $health = app(ContentHealth::class);

        return [
            'cards' => [
                [
                    'label' => 'Pages',
                    'value' => $health->pages(),
                    'icon' => 'heroicon-o-document-duplicate',
                    'tint' => 'blue',
                    'pending' => null,
                ],
                [
                    'label' => 'Posts',
                    'value' => $health->publishedPosts(),
                    'icon' => 'heroicon-o-newspaper',
                    'tint' => 'violet',
                    // The amber pill only appears where a real pending state
                    // exists; a permanent "0 pending" would be noise.
                    'pending' => $health->pendingPosts() ?: null,
                ],
                [
                    'label' => 'Media files',
                    'value' => $health->mediaFiles(),
                    'icon' => 'heroicon-o-photo',
                    'tint' => 'emerald',
                    'pending' => null,
                ],
                [
                    'label' => 'Users',
                    'value' => $health->users(),
                    'icon' => 'heroicon-o-users',
                    'tint' => 'amber',
                    'pending' => null,
                ],
            ],
        ];
    }
}
