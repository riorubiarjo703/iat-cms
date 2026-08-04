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
            <div style="margin-bottom:72px;">
                <div style="display:flex; align-items:center; gap:16px; margin-bottom:32px;">
                    <span style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; white-space:nowrap;">{{ $group['title'] }}</span>
                    <span style="flex:1; height:2px; background:rgba(32,30,29,0.35);"></span>
                </div>

                <div class="scbd-people-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:32px;">
                    @foreach ($group['people'] as $person)
                        <article data-org-card>
                            <div style="overflow:hidden; border:2px solid rgba(32,30,29,0.35); background:#e8e7e6; aspect-ratio:4/3;">
                                @if (filled($person['photo'] ?? null))
                                    <img class="grayscale"
                                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person['photo']) }}"
                                         alt="{{ $person['name'] }}"
                                         loading="lazy"
                                         style="width:100%; height:100%; object-fit:contain; display:block;">
                                @endif
                            </div>
                            <div style="font-weight:800; font-size:16px; letter-spacing:-0.01em; margin-top:14px;">{{ $person['name'] }}</div>
                            @if (filled($person['role'] ?? null))
                                <div style="font-size:11px; letter-spacing:0.16em; text-transform:uppercase; color:rgba(32,30,29,0.6); margin-top:4px;">{{ $person['role'] }}</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
@endif
