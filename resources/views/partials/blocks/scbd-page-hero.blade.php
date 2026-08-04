@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $body = BlockData::t($data, 'body', $locale);
    $image = BlockData::get($data, 'image');
    $caption = BlockData::get($data, 'image_caption');

    // Blank lines separate paragraphs; single newlines stay inside one.
    $paragraphs = $body ? preg_split('/\n\s*\n/', trim($body)) : [];

    // With an image the heading sits on top of it and has to be light; without
    // one the section is an ordinary light page and the ink colour returns.
    $ink = $image ? '#f3f2f2' : '#201e1d';
    $body_ink = $image ? 'rgba(243,242,242,0.82)' : 'rgba(32,30,29,0.8)';
@endphp

<section id="top"
         @class(['scbd-page-hero', 'scbd-pad-top' => ! $image])
         style="position:relative; box-sizing:border-box; {{ $image ? 'color:'.$ink.';' : '' }}">

    @if ($image)
        {{-- The background layer. data-parallax-wrap is what the intro timeline
             wipes open, and it also clips the parallax overscan so the image
             cannot add a scrollbar. --}}
        <div data-parallax-wrap style="position:absolute; inset:0; overflow:hidden; z-index:0;">
            <img data-parallax
                 class="grayscale"
                 src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                 alt="{{ $caption ?: $heading }}"
                 style="position:absolute; inset:-12% 0; width:100%; height:124%; object-fit:cover;">
            {{-- A scrim, not an opacity on the image: the heading has to stay
                 legible over whichever photograph an editor uploads. --}}
            <div style="position:absolute; inset:0; background:linear-gradient(180deg, rgba(20,19,18,0.72) 0%, rgba(20,19,18,0.52) 45%, rgba(20,19,18,0.86) 100%);"></div>
        </div>
    @endif

    <div class="scbd-page-hero-inner" style="position:relative; z-index:1;">
        @if ($eyebrow)
            <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:20px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
        @endif

        {{-- Smaller than the homepage hero on purpose: interior page titles are
             whole phrases rather than three short lines, and 9.2vw pushed
             "property developer" off the right edge. --}}
        <h1 data-split class="scbd-h1" style="font-size:clamp(36px,6.2vw,104px); line-height:0.92; letter-spacing:-0.04em; margin:0 0 40px; text-transform:uppercase; overflow-wrap:break-word; color:{{ $ink }};" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h1>

        @if ($paragraphs)
            <div style="max-width:820px;" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">
                @foreach ($paragraphs as $paragraph)
                    <p data-fade style="font-size:16px; line-height:1.8; color:{{ $body_ink }}; margin:0 0 20px;">{{ trim($paragraph) }}</p>
                @endforeach
            </div>
        @endif
    </div>

    @if ($image && $caption)
        <div style="position:absolute; left:0; bottom:0; z-index:2; background:#ec3013; color:#f3f2f2; padding:12px 20px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase;">{{ $caption }}</div>
    @endif
</section>
