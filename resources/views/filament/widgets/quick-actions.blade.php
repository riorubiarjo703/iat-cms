<div class="scbd-card">
    <div class="scbd-card-head">
        <h3 class="scbd-card-title">Quick actions</h3>
    </div>

    <div class="scbd-actions">
        @foreach ($actions as $action)
            <a href="{{ $action['url'] }}" class="scbd-action">
                <span class="scbd-action-icon scbd-tint-{{ $action['tint'] }}">
                    <x-filament::icon :icon="$action['icon']" />
                </span>
                <span class="scbd-action-label">{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
