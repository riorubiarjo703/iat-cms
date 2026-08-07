<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('facilities.view');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $user->can('facilities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.create');
    }

    public function update(User $user, Facility $facility): bool
    {
        return $user->can('facilities.update');
    }

    public function delete(User $user, Facility $facility): bool
    {
        return $user->can('facilities.delete');
    }

    // Filament's bulk "Delete selected" checks deleteAny(), not delete() —
    // see the note on PagePolicy::deleteAny() for why an undefined ability
    // method is not a safe default to leave missing.
    public function deleteAny(User $user): bool
    {
        return $user->can('facilities.delete');
    }

    // The table is reorderable('sort'); reorder() is another ability that
    // falls through to allow-by-default when a policy exists but the method
    // does not (see HasAuthorization::getReorderAuthorizationResponse()).
    // Treated as an update, the same as the form's own sort field.
    public function reorder(User $user): bool
    {
        return $user->can('facilities.update');
    }
}
