@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);

    $entries = collect(BlockData::get($data, 'entries', []))
        ->filter(fn ($entry) => is_array($entry) && filled($entry['year'] ?? null))
        ->values();
@endphp

@if ($entries->isNotEmpty())
    <section id="milestones" class="scbd-pad">
        @if ($heading)
            <h2 data-split class="scbd-h2" style="font-size:clamp(30px,4.6vw,72px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase; margin:0 0 80px; max-width:16ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        @endif

        {{-- One rule down the middle with the years pinned to it, and the cards
             alternating either side: odd rows put the text left, even rows
             mirror them. The alternation is a class rather than :nth-child so
             the single-column mobile layout can ignore it wholesale. --}}
        <div class="scbd-tl">
            @foreach ($entries as $entry)
                <article data-timeline-card @class(['scbd-tl-row', 'scbd-tl-row-flip' => $loop->even])>
                    <div class="scbd-tl-marker">{{ $entry['year'] }}</div>

                    <div class="scbd-tl-text">
                        <h3 class="scbd-tl-title">{{ $entry['title'] ?? '' }}</h3>
                        @if (filled($entry['body'] ?? null))
                            <p class="scbd-tl-body">{{ $entry['body'] }}</p>
                        @endif
                    </div>

                    <div class="scbd-tl-media">
                        @if (filled($entry['image'] ?? null))
                            <div class="scbd-tl-frame">
                                <img class="grayscale"
                                     src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($entry['image']) }}"
                                     alt="{{ $entry['title'] ?? $entry['year'] }}"
                                     loading="lazy">
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
