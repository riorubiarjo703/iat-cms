<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * `User::canAccessPanel()` now defers to `$this->can('admin.access')`, which
 * only a role grants — a bare `User::factory()->create()` is refused at the
 * panel door. Every feature test written before roles existed drives the
 * panel that way, so this is the one place that knows how to seed the roles
 * and hand back an actor that gets past the gate, rather than three lines of
 * seed+create+assignRole copied into every class.
 */
trait ActsAsSuperAdmin
{
    /**
     * Seeds the roles and permissions, creates a user holding super_admin,
     * and logs the test in as them.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsSuperAdmin(array $attributes = []): static
    {
        return $this->actingAs($this->superAdmin($attributes));
    }

    /**
     * As above, without logging in — for the rare case a test needs the
     * actor itself rather than just to be signed in as them.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function superAdmin(array $attributes = []): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create($attributes);
        $user->assignRole('super_admin');

        return $user->fresh();
    }
}
