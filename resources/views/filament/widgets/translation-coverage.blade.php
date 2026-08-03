<div class="scbd-card scbd-coverage">
    <div class="scbd-card-head">
        <h3 class="scbd-card-title">Translation coverage</h3>
        <p class="scbd-card-sub">Share of translatable fields with a value in each language</p>
    </div>

    @if (! $hasContent)
        {{-- No model composes HasTranslatableFields with any declared fields.
             Rendering empty bars would suggest a translation failure. --}}
        <p class="scbd-empty">No translatable content is registered yet.</p>
    @else
        <div class="scbd-bars">
            @foreach ($locales as $code => $locale)
                <div class="scbd-bar-row">
                    <span class="scbd-bar-label">{{ $locale['label'] }}</span>

                    <div class="scbd-bar-track">
                        <div class="scbd-bar-fill" style="width: {{ $locale['percent'] ?? 0 }}%"></div>
                    </div>

                    <span class="scbd-bar-value">
                        {{-- null means there is nothing to translate, which is
                             not 0% — that would read as total failure. --}}
                        @if ($locale['percent'] === null)
                            —
                        @else
                            {{ $locale['percent'] }}%
                        @endif
                    </span>

                    <span class="scbd-bar-count">{{ $locale['filled'] }}/{{ $locale['total'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
