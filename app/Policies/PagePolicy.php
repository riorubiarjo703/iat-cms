<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('pages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pages.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('pages.update');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('pages.delete');
    }

    /**
     * Filament's bulk "Delete selected" authorizes deleteAny(), not delete().
     *
     * An ability method a policy does not define is *allowed*, not denied —
     * Filament's authorization helper falls through to Response::allow() when
     * the method is missing and strict mode is off. So while this was absent,
     * a content editor with pages.view but no pages.delete was correctly
     * refused the per-row delete and could still select every page and bulk
     * delete the lot.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('pages.delete');
    }

    // restore(), forceDelete() and replicate() are absent on purpose: no
    // model in this catalogue uses SoftDeletes, and no resource registers a
    // ReplicateAction, so Filament never asks for them. reorder() is also
    // absent: the three resources with reorderable() tables (DistrictPlaces,
    // Stats, Facilities) carry no policy at all and are out of this
    // catalogue's scope. If any of that changes, add the method rather than
    // assume the missing-method default is safe — it isn't.
}
