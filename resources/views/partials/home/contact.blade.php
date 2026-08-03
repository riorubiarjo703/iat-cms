@php
    $locale = $data->settings->default_locale ?? 'en';
    $contactHeading = $data->i18n[$locale]['contacth'] ?? '';
    $addressLines = $data->settings->contact_address ? e($data->settings->contact_address) : null;

    // This section is the site's footer. Its Sitemap column renders whichever
    // menu is assigned to the footer location; top-level items with children
    // would become sub-columns, but this design uses a single flat column, so
    // children are flattened in alongside their parent.
    $footerMenu = \App\Support\MenuRenderer::byLocation(\App\Support\MenuLocations::FOOTER);
    $sitemapLinks = $footerMenu->flatMap(function ($item) {
        $children = $item->children->where('is_active', true);

        // A parent that only groups children is a heading, not a link.
        return $children->isNotEmpty() ? $children : collect([$item]);
    });

    $social = \App\Support\SocialNetworks::configured($data->settings->social);
@endphp

<section id="contact" style="background:#ec3013; color:#f3f2f2; padding:120px 40px;">
    <h2 data-split style="font-size:clamp(46px,8vw,150px); line-height:0.86; letter-spacing:-0.045em; margin:0 0 56px; text-transform:uppercase;" data-i18n="contacth">{!! $contactHeading !!}</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:2px; background:rgba(243,242,242,0.3); border:2px solid rgba(243,242,242,0.3);">
        <div style="background:#ec3013; padding:28px;">
            <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Address</div>
            @if ($addressLines)
                <div style="font-size:14px; line-height:1.6;">{!! nl2br($addressLines) !!}</div>
            @endif
        </div>
        <div style="background:#ec3013; padding:28px;">
            <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Contact</div>
            <div style="font-size:14px; line-height:1.6;">
                @if ($data->settings->contact_phone)
                    Tel {{ $data->settings->contact_phone }}<br>
                @endif
                @if ($data->settings->contact_email)
                    {{ $data->settings->contact_email }}
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
    <div style="display:flex; justify-content:space-between; gap:24px; margin-top:32px; font-size:11px; letter-spacing:0.14em; text-transform:uppercase; opacity:0.8;">
        <span>PT Danayasa Arthatama — developer &amp; operator of SCBD</span>
        <span>© 2026 All rights reserved</span>
    </div>
</section>
