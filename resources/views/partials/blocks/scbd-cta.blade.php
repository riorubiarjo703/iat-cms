@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $heading = BlockData::t($data, 'heading', $locale);
    $body = BlockData::t($data, 'body', $locale);
    $buttonLabel = BlockData::t($data, 'button_label', $locale);
    $buttonUrl = BlockData::get($data, 'button_url');
@endphp

@if ($heading)
    {{-- The footer band is the same red. Dropping the bottom padding lets the
         two meet as one block rather than stacking 160px of empty red between
         them — the same treatment the homepage's contact heading uses. --}}
    <section class="scbd-cta" style="background:#ec3013; color:#f3f2f2; padding:80px 40px 0; text-align:center;">
        <h2 data-split class="scbd-h2" style="font-size:clamp(28px,4vw,56px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 20px;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>

        @if ($body)
            <p data-fade style="font-size:14px; line-height:1.7; margin:0 auto 32px; max-width:60ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">{{ $body }}</p>
        @endif

        @if ($buttonLabel && $buttonUrl)
            <a href="{{ $buttonUrl }}"
               data-magnetic
               style="display:inline-block; background:#f3f2f2; color:#201e1d; text-decoration:none; font-weight:800; font-size:13px; letter-spacing:0.1em; text-transform:uppercase; padding:16px 28px;"
               data-i18n="{{ BlockData::i18nKey($blockId, 'button_label') }}">{{ $buttonLabel }}</a>
        @endif
    </section>
@endif
