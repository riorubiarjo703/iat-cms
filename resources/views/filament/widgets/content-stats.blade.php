<div class="scbd-stats">
    @foreach ($cards as $card)
        <div class="scbd-stat">
            <span class="scbd-stat-icon scbd-tint-{{ $card['tint'] }}">
                <x-filament::icon :icon="$card['icon']" />
            </span>

            <p class="scbd-stat-label">{{ $card['label'] }}</p>

            <p class="scbd-stat-value">
                {{-- An em dash means "could not count" — e.g. the media manager
                     is absent. Zero would claim the feature is empty. --}}
                {{ $card['value'] ?? '—' }}

                @if ($card['pending'])
                    <span class="scbd-pending">{{ $card['pending'] }} pending</span>
                @endif
            </p>
        </div>
    @endforeach
</div>
