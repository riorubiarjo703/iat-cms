@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $heading = BlockData::t($data, 'heading', $locale) ?: 'Corporate culture';
    $acronym = BlockData::t($data, 'acronym', $locale);

    $raw = BlockData::get($data, 'values', []);
    $values = $raw[$locale] ?? $raw[\App\Models\SiteSetting::FALLBACK_LOCALE] ?? [];
    $values = collect(is_array($values) ? $values : [])->filter(fn ($v) => filled($v['name'] ?? null))->values();
@endphp

@if ($values->isNotEmpty())
    {{-- The headline stays put while the values pass it: the section is one
         tall dark frame, with a sticky left column and a scrolling right one.
         position:sticky needs the section itself to be the scroll context, so
         nothing here may set overflow on an ancestor of the sticky element. --}}
    <section id="values" class="scbd-values-section">
        <div class="scbd-values-inner">
            <div class="scbd-values-sticky">
                <h2 class="scbd-h2 scbd-values-title" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{{ $heading }}</h2>

                @if ($acronym)
                    <div class="scbd-values-acronym" data-i18n="{{ BlockData::i18nKey($blockId, 'acronym') }}">{{ $acronym }}</div>
                @endif

                <div class="scbd-values-count">{{ str_pad((string) $values->count(), 2, '0', STR_PAD_LEFT) }} principles</div>
            </div>

            <ol class="scbd-values-list">
                @foreach ($values as $value)
                    <li data-fade class="scbd-values-item">
                        <span class="scbd-values-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <div class="scbd-values-name">{{ $value['name'] }}</div>
                            @if (filled($value['description'] ?? null))
                                <p class="scbd-values-desc">{{ $value['description'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
