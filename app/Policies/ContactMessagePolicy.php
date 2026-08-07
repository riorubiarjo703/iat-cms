<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('contacts.view');
    }

    /**
     * Explicit, not omitted: an undefined ability method is *allowed* by
     * Filament's non-strict authorization (it calls Response::allow() when
     * the policy exists but lacks the method), not denied. There is no
     * contacts.create permission because messages arrive from the public
     * contact form, never from the panel — ContactMessageResource currently
     * has no create route, so this costs nothing today, but the day a create
     * page is added it would otherwise open silently.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('contacts.update');
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('contacts.delete');
    }

    // Filament's bulk "Delete selected" checks deleteAny(), not delete() —
    // see the note on PagePolicy::deleteAny() for why an undefined ability
    // method is not a safe default to leave missing.
    public function deleteAny(User $user): bool
    {
        return $user->can('contacts.delete');
    }
}
