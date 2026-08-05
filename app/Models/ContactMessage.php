<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAsUnread(): void
    {
        $this->forceFill(['read_at' => null])->save();
    }

    /** @param  Builder<self>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
