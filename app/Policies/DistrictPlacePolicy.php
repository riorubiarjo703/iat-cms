<?php

namespace App\Policies;

use App\Models\DistrictPlace;
use App\Models\User;

class DistrictPlacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('district-places.view');
    }

    public function view(User $user, DistrictPlace $districtPlace): bool
    {
        return $user->can('district-places.view');
    }

    public function create(User $user): bool
    {
        return $user->can('district-places.create');
    }

    public function update(User $user, DistrictPlace $districtPlace): bool
    {
        return $user->can('district-places.update');
    }

    public function delete(User $user, DistrictPlace $districtPlace): bool
    {
        return $user->can('district-places.delete');
    }

    // Filament's bulk "Delete selected" checks deleteAny(), not delete() —
    // see the note on PagePolicy::deleteAny() for why an undefined ability
    // method is not a safe default to leave missing.
    public function deleteAny(User $user): bool
    {
        return $user->can('district-places.delete');
    }

    // The table is reorderable('sort'); reorder() is another ability that
    // falls through to allow-by-default when a policy exists but the method
    // does not (see HasAuthorization::getReorderAuthorizationResponse()).
    // Treated as an update, the same as the form's own sort field.
    public function reorder(User $user): bool
    {
        return $user->can('district-places.update');
    }
}
