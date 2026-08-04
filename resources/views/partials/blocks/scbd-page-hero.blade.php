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
@endphp

<section id="top" class="scbd-pad-top" style="position:relative; box-sizing:border-box;">
    <div style="padding-top:40px;">
        @if ($eyebrow)
            <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:20px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
        @endif

        {{-- Smaller than the homepage hero on purpose: interior page titles are
             whole phrases rather than three short lines, and 9.2vw pushed
             "property developer" off the right edge. --}}
        <h1 data-split class="scbd-h1" style="font-size:clamp(36px,6.2vw,104px); line-height:0.92; letter-spacing:-0.04em; margin:0 0 48px; text-transform:uppercase; overflow-wrap:break-word;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h1>

        @if ($paragraphs)
            <div style="max-width:900px; margin-bottom:64px;" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">
                @foreach ($paragraphs as $paragraph)
                    <p data-fade style="font-size:16px; line-height:1.8; color:rgba(32,30,29,0.8); margin:0 0 20px;">{{ trim($paragraph) }}</p>
                @endforeach
            </div>
        @endif
    </div>

    @if ($image)
        <div style="position:relative; height:62vh; min-height:380px; overflow:hidden;" data-parallax-wrap>
            <img data-parallax
                 class="grayscale"
                 src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                 alt="{{ $caption ?: $heading }}"
                 style="position:absolute; inset:-12% 0; width:100%; height:124%; object-fit:cover;">
            @if ($caption)
                <div style="position:absolute; left:0; bottom:0; background:#ec3013; color:#f3f2f2; padding:12px 20px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase;">{{ $caption }}</div>
            @endif
        </div>
    @endif
</section>
