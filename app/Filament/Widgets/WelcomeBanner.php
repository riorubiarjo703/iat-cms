<?php

namespace App\Filament\Widgets;

use App\Support\ContentHealth;
use CybertronianKelvin\Graper\Resources\GraperPageResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WelcomeBanner extends Widget
{
    protected string $view = 'filament.widgets.welcome-banner';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;


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
            'date' => $this->now()->translatedFormat('l, j F Y'),
            'greeting' => $this->greeting(),
            'firstName' => Str::before(auth()->user()?->name ?? '', ' ') ?: 'there',
            'createPageUrl' => GraperPageResource::getUrl('create'),
            // Four figures that are all real queries — the reference's analytics
            // are not available, and fabricating them would be worse than
            // showing something true and duller.
            'figures' => [
                ['label' => 'Languages', 'value' => $health->localesConfigured()],
                ['label' => 'Menu items', 'value' => $health->menuItems()],
                ['label' => 'Users', 'value' => $health->users()],
                ['label' => 'Drafts pending', 'value' => $health->pendingPosts()],
            ],
        ];
    }

    /** Application timezone, not the server's — the greeting is for the user. */
    private function now(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    private function greeting(): string
    {
        $hour = (int) $this->now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
