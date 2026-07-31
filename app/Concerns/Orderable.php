<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Orderable
{
    /**
     * The `id` tiebreak keeps ordering stable when rows share a `sort` value,
     * which is the case for every row created before the first manual reorder.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
