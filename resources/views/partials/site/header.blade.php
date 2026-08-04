{{--
    The site header, on every page.

    Renders whichever menu is assigned to the header location, so a page never
    stores its own navigation — changing the menu changes every page at once.
    That is what makes the header "automatic" without being duplicated per page.

    The data-i18n keys (brandsub, nav1..navN, cta) are what SiteTranslations
    publishes, which is how the client-side switcher changes the nav without a
    reload. They are inert on pages that do not load the animation bundle, as
    are data-magnetic and the locale buttons' cursor:none.
--}}
@php
    $settings = \App\Models\SiteSetting::singleton();
    $tree = \App\Support\MenuRenderer::withKeys();
    $cta = \App\Support\MenuRenderer::cta(\App\Support\MenuLocations::HEADER);
    $brandName = $settings->site_name ?: config('app.name');
    $brandSub = $settings->t('brand_subtitle');
    $locales = \App\Models\SiteSetting::LOCALES;
    $activeLocale = $settings->default_locale ?? 'en';
@endphp

<header data-header style="position:fixed; top:0; left:0; right:0; z-index:900; background:rgba(243,242,242,0.92); backdrop-filter:blur(10px); border-bottom:2px solid rgba(32,30,29,0.4);">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:32px; padding:14px 40px;">
        <a href="#top" data-magnetic style="display:flex; align-items:baseline; gap:10px; text-decoration:none; color:#201e1d;">
            @if ($settings->logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) }}"
                     alt="{{ $brandName }}"
                     style="height:26px; width:auto; display:block;">
            @else
                {{-- No logo uploaded: the brand must still be visible, so fall back
                     to the configured site name rather than a hardcoded string. --}}
                <span style="font-weight:800; font-size:22px; letter-spacing:-0.03em;">{{ $brandName }}</span>
            @endif
            <span style="font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(32,30,29,0.55);" data-i18n="brandsub">{{ $brandSub }}</span>
        </a>
        <nav style="display:flex; align-items:center; gap:28px;">
            <ul class="scbd-nav">
                @foreach ($tree as $node)
                    @include('partials.site.nav-item', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>
            <div style="display:flex; align-items:center; gap:2px; border-left:1px solid rgba(32,30,29,0.3); padding-left:20px;">
                @foreach ($locales as $code => $label)
                    <button data-lang="{{ $code }}"
                            style="border:0; background:{{ $code === $activeLocale ? '#201e1d' : 'transparent' }}; color:{{ $code === $activeLocale ? '#f3f2f2' : '#201e1d' }}; font-family:inherit; font-weight:800; font-size:11px; letter-spacing:0.1em; padding:6px 9px; cursor:none;">{{ strtoupper($code) }}</button>
                @endforeach
            </div>
            @if ($cta)
                <a href="{{ $cta->resolveUrl() }}"
                   class="btn btn-primary"
                   data-magnetic
                   @if ($cta->target && $cta->target !== '_self') target="{{ $cta->target }}" @endif
                   style="justify-content:flex-start; cursor:none;"
                   data-i18n="cta">{{ $cta->t('label') }}</a>
            @endif
        </nav>
    </div>
</header>
