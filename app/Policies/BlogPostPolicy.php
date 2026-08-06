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
}
