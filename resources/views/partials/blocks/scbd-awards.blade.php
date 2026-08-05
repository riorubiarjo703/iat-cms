@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);

    $items = collect(BlockData::get($data, 'items', []))
        ->filter(fn ($i) => is_array($i) && filled($i['title'] ?? null))
        ->values();

    // Grouped by year, newest first, with undated entries last rather than
    // dropped. groupBy preserves each group's internal order, so an editor's
    // arrangement within a year survives.
    $groups = $items->groupBy(fn (array $i): string => trim((string) ($i['year'] ?? '')))
        ->sortKeysUsing(fn (string $a, string $b): int => match (true) {
            $a === '' => 1,
            $b === '' => -1,
            default => strcmp($b, $a),
        });
@endphp

@if ($items->isNotEmpty())
    {{-- An index, not a thumbnail wall. Five of these are ISO certificates
         whose standard number is the only thing that tells them apart, and a
         number is text — so the text leads and the scan is what you summon.

         Each row is a button: the certificate opens on click, on Enter, and on
         Space, so the gallery is not a mouse-only feature. The hover preview
         is an enhancement layered on top of that, not the way in. --}}
    <section id="awards" class="scbd-pad scbd-awards" data-awards>
        @if ($heading)
            <h2 data-split class="scbd-h2 scbd-awards-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        @endif

        @foreach ($groups as $year => $group)
            <div class="scbd-awards-group">
                <div class="scbd-awards-year">{{ $year !== '' ? $year : 'Undated' }}</div>

                <ul class="scbd-awards-list">
                    @foreach ($group as $item)
                        @php $image = \App\Support\MediaUrl::resolve($item['image'] ?? null); @endphp

                        <li>
                            <button type="button"
                                    class="scbd-awards-row"
                                    data-award-row
                                    @if ($image) data-award-src="{{ $image }}" @endif
                                    data-award-title="{{ $item['title'] }}"
                                    @if (! $image) disabled aria-disabled="true" @endif>
                                {{-- The thumbnail is the Flip origin on touch, where
                                     there is no hover preview to grow from. --}}
                                <span class="scbd-awards-thumb" aria-hidden="true">
                                    @if ($image)
                                        <img src="{{ $image }}" alt="" loading="lazy">
                                    @endif
                                </span>

                                <span class="scbd-awards-title">{{ $item['title'] }}</span>

                                @if (filled($item['issuer'] ?? null))
                                    <span class="scbd-awards-issuer">{{ $item['issuer'] }}</span>
                                @endif

                                <span class="scbd-awards-open" aria-hidden="true">
                                    @if ($image) View @else No scan @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- The cursor-following preview. One element reused for every row:
             swapping its src beats mounting one preview per certificate. --}}
        <div class="scbd-awards-preview" data-award-preview aria-hidden="true">
            <img alt="">
        </div>
    </section>

    {{-- The reader. Outside the section so no transformed or clipping ancestor
         can trap it — the same trap that caught the sidebar flyout and the
         mobile drawer. --}}
    <div class="scbd-awards-reader" data-award-reader hidden>
        <div class="scbd-awards-reader-backdrop" data-award-close></div>

        <figure class="scbd-awards-reader-figure">
            <img data-award-reader-img alt="">
            <figcaption data-award-reader-caption></figcaption>
        </figure>

        <button type="button" class="scbd-awards-reader-close" data-award-close aria-label="Close certificate">&times;</button>
    </div>
@endif
