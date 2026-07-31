<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Activatable
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
