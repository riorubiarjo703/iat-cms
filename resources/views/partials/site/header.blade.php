{{--
    The site header for standalone pages.

    Renders whichever menu is assigned to the header location, so a page never
    stores its own navigation — changing the menu changes every page at once.
    That is what makes the header "automatic" without being duplicated per page.
--}}
@php
    $settings = \App\Models\SiteSetting::singleton();
    $links = \App\Support\MenuRenderer::byLocation(\App\Support\MenuLocations::HEADER);
    $cta = \App\Support\MenuRenderer::cta(\App\Support\MenuLocations::HEADER);
    $brandName = $settings->site_name ?: config('app.name');
@endphp

<header style="position:sticky; top:0; z-index:900; background:rgba(243,242,242,0.92); backdrop-filter:blur(10px); border-bottom:2px solid rgba(32,30,29,0.4);">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:32px; padding:14px 40px;">
        {{-- data-magnetic is inert without the animation bundle, so it is safe
             on standard pages and keeps builder pages identical to the
             hand-built homepage. --}}
        <a href="{{ url('/') }}" data-magnetic style="display:flex; align-items:baseline; gap:10px; text-decoration:none; color:#201e1d;">
            @if ($settings->logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) }}"
                     alt="{{ $brandName }}" style="height:26px; width:auto; display:block;">
            @else
                <span style="font-weight:800; font-size:22px; letter-spacing:-0.03em;">{{ $brandName }}</span>
            @endif
        </a>

        <nav style="display:flex; align-items:center; gap:28px;">
            @foreach ($links as $link)
                <a href="{{ $link->resolveUrl() }}"
                   @if ($link->target && $link->target !== '_self') target="{{ $link->target }}" rel="noopener" @endif
                   style="font-size:12px; letter-spacing:0.14em; text-transform:uppercase; text-decoration:none; color:#201e1d;">{{ $link->t('label') }}</a>
            @endforeach

            @if ($cta)
                <a href="{{ $cta->resolveUrl() }}" data-magnetic
                   @if ($cta->target && $cta->target !== '_self') target="{{ $cta->target }}" rel="noopener" @endif
                   style="background:#ec3013; color:#f3f2f2; padding:10px 18px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; text-decoration:none;">{{ $cta->t('label') }}</a>
            @endif
        </nav>
    </div>
</header>
