@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);

    $facilities = \App\Models\Facility::query()->active()->ordered()->get();
@endphp

@if ($facilities->isNotEmpty())
    <section id="facilities" class="scbd-pad">
        <div style="border-bottom:2px solid rgba(32,30,29,0.4); padding-bottom:24px; margin-bottom:80px;">
            @if ($eyebrow)
                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:16px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
            @endif
            <h2 data-split class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        </div>

        <div class="scbd-guide-rows">
            @foreach ($facilities as $facility)
                @php
                    $imageLeft = $loop->index % 2 === 1;
                    $facilityEyebrow = $facility->t('eyebrow', $locale);
                    $statLabel = $facility->t('stat_label', $locale);
                @endphp

                {{-- See scbd-places: one @class, never one beside a class
                     attribute. --}}
                <article @class(['scbd-guide-row', 'scbd-card-split', 'scbd-guide-row-flip' => $imageLeft])>
                    <div data-fade class="scbd-guide-row-text">
                        <div>
                            @if ($facilityEyebrow)
                                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:12px;">{{ $facilityEyebrow }}</div>
                            @endif

                            <h3 class="scbd-guide-row-title">{{ $facility->t('title', $locale) }}</h3>

                            @if ($facility->t('body', $locale))
                                <p class="scbd-guide-row-body">{{ $facility->t('body', $locale) }}</p>
                            @endif
                        </div>

                        @if ($statLabel || $facility->stat_value)
                            <div class="scbd-guide-stat">
                                @if ($statLabel)
                                    <div class="scbd-guide-stat-label">{{ $statLabel }}</div>
                                @endif
                                @if ($facility->stat_value)
                                    <div class="scbd-guide-stat-value scbd-guide-stat-value-sm">{{ $facility->stat_value }}</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div data-reveal class="scbd-guide-row-media">
                        @if ($image = \App\Support\MediaUrl::resolve($facility->image))
                            <img class="grayscale"
                                 src="{{ $image }}"
                                 alt="{{ $facility->t('title', $locale) }}"
                                 loading="lazy">
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
