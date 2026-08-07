{{--
    The site's footer band: address, contact, sitemap and social.

    Included solely by the site footer, so every page renders it the same way
    from one source. Takes $settings only — everything else it needs is
    read from the menus and Site Settings, which is what makes it identical on
    every page without being stored per page.
--}}
@php
    $addressLines = $settings->contact_address ? e($settings->contact_address) : null;
    $settings = \App\Models\SiteSetting::singleton();
    $footerMenu = \App\Support\MenuRenderer::byLocation(\App\Support\MenuLocations::FOOTER);
    $sitemapLinks = $footerMenu->flatMap(function ($item) {
        $children = $item->loadedChildren()->filter(fn ($child) => $child->isVisible());

        // A parent that only groups children is a heading, not a link.
        return $children->isNotEmpty() ? $children : collect([$item]);
    });

    $social = \App\Support\SocialNetworks::configured($settings->social);
@endphp

<div class="scbd-footer-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:2px; background:rgba(243,242,242,0.3); border:2px solid rgba(243,242,242,0.3);">
    <div style="background:#ec3013; padding:28px;">
        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Address</div>
        @if ($addressLines)
            <div style="font-size:14px; line-height:1.6;">{!! nl2br($addressLines) !!}</div>
        @endif
    </div>
    <div style="background:#ec3013; padding:28px;">
        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Contact</div>
        <div style="font-size:14px; line-height:1.6;">
            @if ($settings->contact_phone)
                Tel {{ $settings->contact_phone }}<br>
            @endif
            @if ($settings->contact_email)
                {{ $settings->contact_email }}
            @endif
        </div>
    </div>
    <div style="background:#ec3013; padding:28px;">
        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Sitemap</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:14px;">
            @foreach ($sitemapLinks as $link)
                <a href="{{ $link->resolveUrl() }}"
                   @if ($link->target && $link->target !== '_self') target="{{ $link->target }}" rel="noopener" @endif
                   style="color:#f3f2f2; text-decoration:none;">{{ $link->t('label') }}</a>
            @endforeach
        </div>
    </div>
    <div style="background:#ec3013; padding:28px;">
        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Social</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:14px;">
            @foreach ($social as $network)
                <a href="{{ $network['url'] }}" target="_blank" rel="noopener"
                   style="color:#f3f2f2; text-decoration:none;">{{ $network['label'] }}</a>
            @endforeach
        </div>
    </div>
</div>
<div>
    <h2 class="scbd-h1">{{ $settings->site_name }}</h2>
</div>
<div class="scbd-footer-meta" style="display:flex; justify-content:space-between; gap:24px; margin-top:32px; font-size:11px; letter-spacing:0.14em; text-transform:uppercase; opacity:0.8;">
    <span>PT Danayasa Arthatama — developer &amp; operator of SCBD</span>
    <span>© {{ now()->year }} All rights reserved</span>
</div>
