@php
    $marqueeText = $data->content->t('marquee_text') ?? '';
@endphp

<div style="border-top:2px solid rgba(32,30,29,0.4); border-bottom:2px solid rgba(32,30,29,0.4); overflow:hidden; padding:16px 0; background:#f3f2f2;">
    <div data-marquee style="display:flex; gap:48px; white-space:nowrap; will-change:transform;">
        @for ($i = 0; $i < 4; $i++)
            <span data-i18n="marquee" style="font-weight:800; font-size:26px; letter-spacing:-0.02em; text-transform:uppercase;">{{ $marqueeText }}</span>
        @endfor
    </div>
</div>
