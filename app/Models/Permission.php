<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie's permission, extended for the same two reasons as App\Models\Role:
 * the `is_system` column, and policy auto-discovery.
 */
class Permission extends SpatiePermission
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
