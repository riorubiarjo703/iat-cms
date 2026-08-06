<?php

namespace App\Policies;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use App\Models\User;

class BlogCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('categories.update');
    }

    public function delete(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('categories.delete');
    }
}
