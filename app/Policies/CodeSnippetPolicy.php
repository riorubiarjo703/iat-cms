<?php

namespace App\Policies;

use App\Models\CodeSnippet;
use App\Models\User;

class CodeSnippetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('code-snippets.view');
    }

    public function view(User $user, CodeSnippet $codeSnippet): bool
    {
        return $user->can('code-snippets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('code-snippets.create');
    }

    public function update(User $user, CodeSnippet $codeSnippet): bool
    {
        return $user->can('code-snippets.update');
    }

    public function delete(User $user, CodeSnippet $codeSnippet): bool
    {
        return $user->can('code-snippets.delete');
    }
}
