<div class="scbd-banner">
    <div class="scbd-banner-head">
        <div>
            <p class="scbd-eyebrow">{{ $date }}</p>
            <h2 class="scbd-greeting">{{ $greeting }}, <span>{{ $firstName }}</span></h2>
        </div>

        <a href="{{ $createPageUrl }}" class="scbd-cta">
            <x-filament::icon icon="heroicon-o-plus" class="scbd-cta-icon" />
            New Page
        </a>
    </div>

    <dl class="scbd-figures">
        @foreach ($figures as $figure)
            <div class="scbd-figure">
                {{-- null means the count could not be taken, which is not the
                     same as zero; an em dash says so rather than implying none. --}}
                <dd class="scbd-figure-value">{{ $figure['value'] ?? '—' }}</dd>
                <dt class="scbd-figure-label">{{ $figure['label'] }}</dt>
            </div>
        @endforeach
    </dl>
</div>
