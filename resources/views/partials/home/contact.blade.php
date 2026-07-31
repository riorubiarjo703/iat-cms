@php
    $locale = $data->settings->default_locale ?? 'en';
    $contactHeading = $data->i18n[$locale]['contacth'] ?? '';
    $addressLines = $data->content->contact_address ? e($data->content->contact_address) : null;
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
                @if ($data->content->contact_phone)
                    Tel {{ $data->content->contact_phone }}<br>
                @endif
                @if ($data->content->contact_email)
                    {{ $data->content->contact_email }}
                @endif
            </div>
        </div>
        <div style="background:#ec3013; padding:28px;">
            <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Sitemap</div>
            <div style="display:flex; flex-direction:column; gap:6px; font-size:14px;">
                <a href="https://scbd.com/menu/page/profile" style="color:#f3f2f2; text-decoration:none;">Company profile</a>
                <a href="https://scbd.com/menu/page/milestone" style="color:#f3f2f2; text-decoration:none;">Milestone</a>
                <a href="https://scbd.com/menu/page/places" style="color:#f3f2f2; text-decoration:none;">Place of interest</a>
                <a href="https://scbd.com/menu/page/careers" style="color:#f3f2f2; text-decoration:none;">Careers</a>
            </div>
        </div>
        <div style="background:#ec3013; padding:28px;">
            <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8; margin-bottom:12px;">Social</div>
            <div style="display:flex; flex-direction:column; gap:6px; font-size:14px;">
                <a href="https://web.facebook.com/SCBD.ID" style="color:#f3f2f2; text-decoration:none;">Facebook</a>
                <a href="https://twitter.com/scbd_id" style="color:#f3f2f2; text-decoration:none;">X / Twitter</a>
                <a href="https://www.instagram.com/scbd_official/" style="color:#f3f2f2; text-decoration:none;">Instagram</a>
                <a href="https://www.linkedin.com/feed/" style="color:#f3f2f2; text-decoration:none;">LinkedIn</a>
            </div>
        </div>
    </div>
    <div style="display:flex; justify-content:space-between; gap:24px; margin-top:32px; font-size:11px; letter-spacing:0.14em; text-transform:uppercase; opacity:0.8;">
        <span>PT Danayasa Arthatama — developer &amp; operator of SCBD</span>
        <span>© 2026 All rights reserved</span>
    </div>
</section>
