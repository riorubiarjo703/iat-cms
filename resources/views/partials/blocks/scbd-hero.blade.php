@php
    use App\PageBuilder\BlockData;

    $settings = \App\Models\SiteSetting::singleton();
    $locale = $settings->default_locale ?? 'en';

    $heading = BlockData::t($data, 'heading', $locale);
    $subheading = BlockData::t($data, 'subheading', $locale);
    $image = BlockData::get($data, 'image');

    // Falls back to the address in Site Settings, which is where the old
    // hardcoded version read it from.
    $locationTag = BlockData::get($data, 'location_tag')
        ?: ($settings->contact_address ? str_replace(["\r\n", "\n", "\r"], ' · ', $settings->contact_address) : null);
@endphp

<section id="top" style="position:relative; min-height:100vh; padding:120px 40px 0; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; overflow:hidden;">
    <div style="display:grid; grid-template-columns:1fr auto; align-items:end; gap:32px; padding-top:40px;">
        <h1 data-split style="font-size:clamp(52px,9.2vw,168px); line-height:0.88; letter-spacing:-0.045em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h1>
        <div style="max-width:300px; padding-bottom:12px;">
            <div style="height:2px; background:#201e1d; margin-bottom:14px;"></div>
            <p style="font-size:14px; line-height:1.6; margin:0; color:rgba(32,30,29,0.75);" data-i18n="{{ BlockData::i18nKey($blockId, 'subheading') }}">{{ $subheading }}</p>
        </div>
    </div>

    <div style="position:relative; margin-top:48px; height:56vh; min-height:340px; overflow:hidden;" data-parallax-wrap>
        @if ($image)
            <img data-parallax
                 class="grayscale"
                 src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                 alt="{{ $heading }}"
                 style="position:absolute; inset:-12% 0; width:100%; height:124%; object-fit:cover;">
        @else
            {{-- No image: a tinted panel keeps the parallax target present so
                 the scroll animation has something to move. --}}
            <div data-parallax style="position:absolute; inset:-12% 0; width:100%; height:124%; background:#201e1d; opacity:0.08;"></div>
        @endif
        @if ($locationTag)
            <div style="position:absolute; left:0; bottom:0; background:#ec3013; color:#f3f2f2; padding:12px 20px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase;">{{ $locationTag }}</div>
        @endif
    </div>
</section>
