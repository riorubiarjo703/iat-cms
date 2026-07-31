<?php

namespace App\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Mount/save cycle for Filament pages that edit a single always-present row.
 *
 * The implementing page must declare `public ?array $data = []` and a `form()`
 * schema whose `statePath` is `'data'`.
 */
trait EditsSingletonRecord
{
    abstract protected function singletonRecord(): Model;

    public function mount(): void
    {
        $this->form->fill($this->singletonRecord()->attributesToArray());
    }

    public function save(): void
    {
        $this->singletonRecord()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();
    }
}
