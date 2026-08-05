@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);

    $places = \App\Models\DistrictPlace::query()->active()->ordered()->get();
@endphp

{{-- Nothing to list means no section, the same guard the homepage's district
     strip uses. --}}
@if ($places->isNotEmpty())
    <section id="places" class="scbd-pad">
        <div style="border-bottom:2px solid rgba(32,30,29,0.4); padding-bottom:24px; margin-bottom:80px;">
            @if ($eyebrow)
                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:16px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
            @endif
            <h2 data-split class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        </div>

        <div class="scbd-guide-rows">
            @foreach ($places as $place)
                @php
                    // Rows alternate side to side. The image column is ordered
                    // rather than re-ordered in markup so that the text always
                    // comes first in the document, for screen readers and for
                    // the single-column layout below 900px.
                    $imageLeft = $loop->index % 2 === 1;
                    $tags = $place->tagList($locale);
                    $statLabel = $place->t('stat_label', $locale);
                @endphp

                {{-- One @class, not a class attribute beside it: Blade emits
                     the directive as its own class attribute, and a second one
                     is ignored by the parser. --}}
                <article @class(['scbd-guide-row', 'scbd-card-split', 'scbd-guide-row-flip' => $imageLeft])>
                    <div data-fade class="scbd-guide-row-text">
                        <div>
                            <h3 class="scbd-guide-row-title">{{ $place->t('title', $locale) }}</h3>

                            @if ($place->t('body', $locale))
                                <p class="scbd-guide-row-body">{{ $place->t('body', $locale) }}</p>
                            @endif

                            @if ($tags)
                                <div class="scbd-guide-tags">
                                    @foreach ($tags as $tag)
                                        <span class="scbd-guide-tag">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($statLabel || $place->stat_value)
                            <div class="scbd-guide-stat">
                                @if ($statLabel)
                                    <div class="scbd-guide-stat-label">{{ $statLabel }}</div>
                                @endif
                                @if ($place->stat_value)
                                    <div class="scbd-guide-stat-value">{{ $place->stat_value }}</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div data-reveal class="scbd-guide-row-media">
                        @if ($image = \App\Support\MediaUrl::resolve($place->image))
                            <img class="grayscale"
                                 src="{{ $image }}"
                                 alt="{{ $place->t('title', $locale) }}"
                                 loading="lazy">
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
