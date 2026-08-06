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

    // No create(): there is no contacts.create permission. Messages arrive
    // from the public contact form, never from the panel, so Filament's
    // canCreate() falls through to denied — which is correct here.

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('contacts.update');
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('contacts.delete');
    }
}
