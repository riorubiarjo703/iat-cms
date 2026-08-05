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

    $headingStyle = 'font-size:clamp(28px,3.4vw,44px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 24px;';
    $frameStyle = 'overflow:hidden; border:2px solid rgba(32,30,29,0.4);';
    $imageStyle = 'width:100%; height:100%; min-height:340px; object-fit:cover; display:block;';
@endphp

<section id="vision" class="scbd-pad">
    {{-- Two rows rather than two columns, and the image side alternates: vision
         reads left-to-right, mission mirrors it. --}}
    <div class="scbd-vm-row" style="max-width:1400px; margin:0 auto 96px;">
        <div>
            <h2 data-split class="scbd-h2" style="{{ $headingStyle }}" data-i18n="{{ BlockData::i18nKey($blockId, 'vision_label') }}">{{ $visionLabel }}</h2>

            @if ($vision)
                <p data-fade style="font-size:clamp(18px,1.6vw,24px); line-height:1.65; color:rgba(32,30,29,0.85); margin:0; max-width:46ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'vision') }}">{{ $vision }}</p>
            @endif
        </div>

        @if ($visionImageUrl = \App\Support\MediaUrl::resolve($visionImage))
            <div style="{{ $frameStyle }}">
                <img data-reveal class="grayscale"
                     src="{{ $visionImageUrl }}"
                     alt="{{ $visionLabel }}"
                     loading="lazy"
                     style="{{ $imageStyle }}">
            </div>
        @endif
    </div>

    <div class="scbd-vm-row scbd-vm-row-flip" style="max-width:1400px; margin:0 auto;">
        @if ($missionImageUrl = \App\Support\MediaUrl::resolve($missionImage))
            <div style="{{ $frameStyle }}">
                <img data-reveal class="grayscale"
                     src="{{ $missionImageUrl }}"
                     alt="{{ $missionLabel }}"
                     loading="lazy"
                     style="{{ $imageStyle }}">
            </div>
        @endif

        <div>
            <h2 data-split class="scbd-h2" style="{{ $headingStyle }}" data-i18n="{{ BlockData::i18nKey($blockId, 'mission_label') }}">{{ $missionLabel }}</h2>

            @if ($mission->isNotEmpty())
                {{-- Each commitment is its own scroll-scrubbed block: the number
                     leads, its text follows a fraction of the scroll behind.
                     Blocks stagger against each other because each carries its
                     own trigger. --}}
                {{-- Still an ordered list: the commitments have a declared
                     order even though the marker no longer counts. The marker
                     is decorative, so it is hidden from assistive tech rather
                     than announced as a character. --}}
                <ol class="scbd-mission-list">
                    @foreach ($mission as $point)
                        <li class="scbd-mission-item" data-scroll-block>
                            <span class="scbd-mission-marker" data-scroll-lead aria-hidden="true"></span>
                            <span class="scbd-mission-text" data-scroll-body>{{ $point }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
</section>
