@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $text = BlockData::t($data, 'text', $locale) ?? '';
    $key = BlockData::i18nKey($blockId, 'text');
@endphp

<div style="border-top:2px solid rgba(32,30,29,0.4); border-bottom:2px solid rgba(32,30,29,0.4); overflow:hidden; padding:16px 0; background:#f3f2f2;">
    <div data-marquee style="display:flex; gap:48px; white-space:nowrap; will-change:transform;">
        {{-- Four copies so the loop has no visible seam at any viewport width. --}}
        @for ($i = 0; $i < 4; $i++)
            <span data-i18n="{{ $key }}" style="font-weight:800; font-size:26px; letter-spacing:-0.02em; text-transform:uppercase;">{{ $text }}</span>
        @endfor
    </div>
</div>
