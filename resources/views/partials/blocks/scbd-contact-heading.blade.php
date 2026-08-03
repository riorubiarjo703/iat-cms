@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);
@endphp

<section id="contact" style="background:#ec3013; color:#f3f2f2; padding:120px 40px 0;">
    <h2 data-split style="font-size:clamp(46px,8vw,150px); line-height:0.86; letter-spacing:-0.045em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
</section>
