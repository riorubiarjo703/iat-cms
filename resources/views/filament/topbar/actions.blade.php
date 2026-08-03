{{--
    Help and Settings buttons for the topbar cluster, injected via TOPBAR_END.
    Filament already supplies search, the theme switcher and the avatar; these
    are the two the reference adds.

    The reference also shows a "Pro" plan-tier badge. That is the source
    product's own upsell — this CMS has no plan tiers, so it is omitted rather
    than rendered as decoration.
--}}
<a
    href="{{ route('filament.admin.pages.dashboard') }}"
    class="fi-topbar-action"
    title="Help"
    aria-label="Help"
>
    <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-5 w-5" />
</a>

<a
    href="{{ \App\Filament\Pages\SiteSettingsPage::getUrl() }}"
    class="fi-topbar-action"
    title="Site settings"
    aria-label="Site settings"
>
    <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-5 w-5" />
</a>
