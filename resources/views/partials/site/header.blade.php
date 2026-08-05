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
    $flags = \App\Models\SiteSetting::LOCALE_FLAGS;
    $activeLocale = $settings->default_locale ?? 'en';
@endphp

{{-- The blur lives in the stylesheet, not here: it is only applied at the
     desktop breakpoint, because backdrop-filter makes the header a containing
     block for its position:fixed drawer. --}}
<header data-header class="scbd-header" style="position:fixed; top:0; left:0; right:0; z-index:900; background:rgba(243,242,242,0.92); border-bottom:2px solid rgba(32,30,29,0.4);">
    <div class="scbd-header-bar">
        {{-- The site root, on every page, so the logo is always the way back to
             the homepage. Not "#top": that only scrolls the page you are
             already on, and leaves the logo dead on every interior page. Not an
             empty href either — that resolves to the current URL and reloads
             wherever you happen to be. --}}
        <a href="{{ route('home') }}" data-magnetic style="display:flex; align-items:baseline; gap:10px; text-decoration:none; color:#201e1d;">
            @if ($logoUrl = \App\Support\MediaUrl::resolve($settings->logo))
                <img src="{{ $logoUrl }}"
                     alt="{{ $brandName }}"
                     style="height:26px; width:auto; display:block;">
            @else
                {{-- No logo uploaded: the brand must still be visible, so fall back
                     to the configured site name rather than a hardcoded string. --}}
                <span style="font-weight:800; font-size:22px; letter-spacing:-0.03em;">{{ $brandName }}</span>
            @endif
            <span style="font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(32,30,29,0.55);" data-i18n="brandsub">{{ $brandSub }}</span>
        </a>
        {{-- Shown only below the desktop breakpoint. Its aria-expanded is
             kept in sync by nav.js so a screen reader knows the drawer state. --}}
        <button type="button" class="scbd-burger" data-nav-toggle aria-label="Open menu" aria-expanded="false" aria-controls="scbd-mobile-nav">
            <span></span><span></span><span></span>
        </button>

        <nav id="scbd-mobile-nav" class="scbd-header-nav">
            <ul class="scbd-nav">
                @foreach ($tree as $node)
                    @include('partials.site.nav-item', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>
            {{-- The trigger's flag and code are rewritten by i18n.js on switch,
                 so it always shows the language you are actually reading. --}}
            <div class="scbd-locales" data-locale-switcher>
                <button type="button" class="scbd-locale-trigger" data-locale-trigger aria-haspopup="true" aria-expanded="false" aria-label="Change language">
                    @if ($flag = $flags[$activeLocale] ?? null)
                        <img src="{{ asset($flag) }}" alt="" width="20" height="14" data-locale-trigger-flag>
                    @endif
                    <span data-locale-trigger-code>{{ strtoupper($activeLocale) }}</span>
                    <svg class="scbd-locale-caret" width="9" height="6" viewBox="0 0 9 6" aria-hidden="true"><path d="M1 1l3.5 3.5L8 1" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                </button>

                <ul class="scbd-locale-menu" data-locale-menu hidden>
                    @foreach ($locales as $code => $label)
                        <li>
                            <button type="button" data-lang="{{ $code }}" @if ($code === $activeLocale) aria-current="true" @endif>
                                @if ($flag = $flags[$code] ?? null)
                                    <img src="{{ asset($flag) }}" alt="" width="20" height="14">
                                @endif
                                <span>{{ $label }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
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

    {{-- Closes the drawer when the page behind it is tapped. --}}
    <div class="scbd-nav-backdrop" data-nav-backdrop hidden></div>
</header>
