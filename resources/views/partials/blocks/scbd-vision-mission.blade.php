@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $visionLabel = BlockData::t($data, 'vision_label', $locale) ?: 'Vision';
    $vision = BlockData::t($data, 'vision', $locale);
    $missionLabel = BlockData::t($data, 'mission_label', $locale) ?: 'Mission';
    $visionImage = BlockData::get($data, 'vision_image');
    $missionImage = BlockData::get($data, 'mission_image');

    // The mission is a per-locale list, with the English one as the fallback so
    // an untranslated locale still shows the commitments.
    $missionRaw = BlockData::get($data, 'mission', []);
    $mission = $missionRaw[$locale] ?? $missionRaw[\App\Models\SiteSetting::FALLBACK_LOCALE] ?? [];
    $mission = collect(is_array($mission) ? $mission : [])
        ->map(fn ($point) => is_array($point) ? ($point['text'] ?? null) : $point)
        ->filter()
        ->values();
@endphp

<section id="vision" class="scbd-pad">
    <div class="scbd-split-2" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); max-width:1400px;">
        <div>
            <h2 data-split class="scbd-h2" style="font-size:clamp(28px,3.4vw,44px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 24px;" data-i18n="{{ BlockData::i18nKey($blockId, 'vision_label') }}">{{ $visionLabel }}</h2>

            @if ($vision)
                <p data-fade style="font-size:17px; line-height:1.75; color:rgba(32,30,29,0.8); margin:0 0 32px; max-width:46ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'vision') }}">{{ $vision }}</p>
            @endif

            @if ($visionImage)
                <div style="overflow:hidden; border:2px solid rgba(32,30,29,0.4);">
                    <img data-reveal class="grayscale"
                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($visionImage) }}"
                         alt="{{ $visionLabel }}"
                         style="width:100%; height:320px; object-fit:cover; display:block;">
                </div>
            @endif
        </div>

        <div>
            <h2 data-split style="font-size:clamp(28px,3.4vw,44px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 24px;" data-i18n="{{ BlockData::i18nKey($blockId, 'mission_label') }}">{{ $missionLabel }}</h2>

            @if ($mission->isNotEmpty())
                <ol style="list-style:none; margin:0 0 32px; padding:0; counter-reset:mission;">
                    @foreach ($mission as $point)
                        <li data-fade style="counter-increment:mission; display:grid; grid-template-columns:44px 1fr; gap:16px; padding:16px 0; border-top:1px solid rgba(32,30,29,0.25);">
                            <span style="font-weight:800; font-size:13px; letter-spacing:0.08em; color:#ec3013;">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span style="font-size:15px; line-height:1.7; color:rgba(32,30,29,0.8);">{{ $point }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($missionImage)
                <div style="overflow:hidden; border:2px solid rgba(32,30,29,0.4);">
                    <img data-reveal class="grayscale"
                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($missionImage) }}"
                         alt="{{ $missionLabel }}"
                         style="width:100%; height:320px; object-fit:cover; display:block;">
                </div>
            @endif
        </div>
    </div>
</section>
