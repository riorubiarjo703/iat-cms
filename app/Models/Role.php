<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's role, extended so this application owns it.
 *
 * Two reasons the subclass exists: the extra `description` and `is_system`
 * columns, and Laravel's policy auto-discovery, which looks for
 * `App\Policies\RolePolicy` beside `App\Models\Role` and would never find a
 * policy for a model living in the vendor namespace.
 */
class Role extends SpatieRole
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
