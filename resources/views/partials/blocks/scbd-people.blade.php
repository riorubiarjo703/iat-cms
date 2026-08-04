@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);

    // array_merge, not the + operator: + keeps the left-hand side's key, so
    // overwriting 'people' on an array that already has one silently does
    // nothing and the value stays a plain array.
    $groups = collect(BlockData::get($data, 'groups', []))
        ->filter(fn ($g) => is_array($g) && filled($g['title'] ?? null))
        ->map(fn ($g) => array_merge($g, [
            'people' => collect($g['people'] ?? [])->filter(fn ($p) => filled($p['name'] ?? null))->values(),
        ]))
        ->filter(fn ($g) => $g['people']->isNotEmpty())
        ->values();
@endphp

@if ($groups->isNotEmpty())
    <section id="people" class="scbd-pad">
        @if ($heading)
            <h2 data-split class="scbd-h2" style="font-size:clamp(30px,4.6vw,72px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase; margin:0 0 64px; max-width:16ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        @endif

        @foreach ($groups as $group)
            @php
                // The track travels -50%, so it holds the run twice over and
                // lands seamlessly on the copy. A short group is repeated first
                // until the run is wide enough to overflow a desktop viewport —
                // otherwise the loop shows empty space instead of a cycle.
                //
                // Below the threshold there is nothing to cycle through: a
                // one-person group repeated to fill the width reads as a
                // rendering fault, not as a roulette. Those groups stay a
                // static row.
                $people = $group['people'];
                $loops = $people->count() >= 3;
                $repeat = $loops ? (int) ceil(8 / $people->count()) : 1;
                $run = collect()->times($repeat, fn () => $people)->flatten(1);
                $passes = $loops ? [false, true] : [false];
            @endphp

            <div style="margin-bottom:72px;">
                <div style="display:flex; align-items:center; gap:16px; margin-bottom:32px;">
                    <span style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; white-space:nowrap;">{{ $group['title'] }}</span>
                    <span style="flex:1; height:2px; background:rgba(32,30,29,0.35);"></span>
                </div>

                {{-- The viewport clips; the track inside it is what moves. --}}
                <div class="scbd-roulette">
                    <div class="scbd-roulette-track" @if ($loops) data-roulette @endif>
                        @foreach ($passes as $isCopy)
                            @foreach ($run as $person)
                                {{-- The second run is the seam filler, not content:
                                     hiding it from assistive tech stops every name
                                     being announced twice. --}}
                                <article class="scbd-roulette-card" @if ($isCopy) aria-hidden="true" @endif>
                                    <div class="scbd-roulette-frame">
                                        @if (filled($person['photo'] ?? null))
                                            <img class="grayscale"
                                                 src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person['photo']) }}"
                                                 alt="{{ $isCopy ? '' : $person['name'] }}"
                                                 loading="lazy">
                                        @endif
                                    </div>
                                    <div class="scbd-roulette-name">{{ $person['name'] }}</div>
                                    @if (filled($person['role'] ?? null))
                                        <div class="scbd-roulette-role">{{ $person['role'] }}</div>
                                    @endif
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </section>
@endif
