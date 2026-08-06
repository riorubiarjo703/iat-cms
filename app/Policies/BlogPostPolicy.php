<?php

namespace App\Policies;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.view');
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        return $user->can('posts.update');
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $user->can('posts.delete');
    }

    // Filament's bulk "Delete selected" checks deleteAny(), not delete() —
    // see the note on PagePolicy::deleteAny() for why an undefined ability
    // method is not a safe default to leave missing.
    public function deleteAny(User $user): bool
    {
        return $user->can('posts.delete');
    }
}
