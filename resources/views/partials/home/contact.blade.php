@php
    $locale = $data->settings->default_locale ?? 'en';
    $contactHeading = $data->i18n[$locale]['contacth'] ?? '';
@endphp

<section id="contact" style="background:#ec3013; color:#f3f2f2; padding:120px 40px;">
    <h2 data-split style="font-size:clamp(46px,8vw,150px); line-height:0.86; letter-spacing:-0.045em; margin:0 0 56px; text-transform:uppercase;" data-i18n="contacth">{!! $contactHeading !!}</h2>
    @include('partials.site.footer-band', ['settings' => $data->settings])
</section>
