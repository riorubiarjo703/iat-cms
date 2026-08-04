@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);

    $entries = collect(BlockData::get($data, 'entries', []))
        ->filter(fn ($entry) => is_array($entry) && filled($entry['year'] ?? null))
        ->values();
@endphp

@if ($entries->isNotEmpty())
    <section id="milestones" style="padding:120px 40px;">
        @if ($heading)
            <h2 data-split style="font-size:clamp(30px,4.6vw,72px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase; margin:0 0 64px; max-width:16ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        @endif

        {{-- A single vertical rule the cards hang off, so the eye follows one
             line down the page rather than a border per card. --}}
        <div style="position:relative; padding-left:0;">
            @foreach ($entries as $entry)
                <article data-timeline-card style="display:grid; grid-template-columns:minmax(120px,180px) 1fr; gap:40px; align-items:start; padding:40px 0; border-top:2px solid rgba(32,30,29,0.35);">
                    <div style="font-weight:800; font-size:clamp(20px,2.4vw,34px); line-height:1; letter-spacing:-0.03em; color:#ec3013;">{{ $entry['year'] }}</div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:32px; align-items:start;">
                        <div>
                            <h3 style="font-size:clamp(19px,2vw,28px); line-height:1.15; letter-spacing:-0.02em; text-transform:uppercase; margin:0 0 12px;">{{ $entry['title'] ?? '' }}</h3>
                            @if (filled($entry['body'] ?? null))
                                <p style="font-size:15px; line-height:1.75; color:rgba(32,30,29,0.75); margin:0;">{{ $entry['body'] }}</p>
                            @endif
                        </div>

                        @if (filled($entry['image'] ?? null))
                            <div style="overflow:hidden; border:2px solid rgba(32,30,29,0.35);">
                                <img class="grayscale"
                                     src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($entry['image']) }}"
                                     alt="{{ $entry['title'] ?? $entry['year'] }}"
                                     loading="lazy"
                                     style="width:100%; height:230px; object-fit:cover; display:block;">
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
