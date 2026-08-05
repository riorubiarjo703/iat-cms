<?php

namespace Database\Factories;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CodeSnippet> */
class CodeSnippetFactory extends Factory
{
    protected $model = CodeSnippet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => SnippetType::Script,
            'position' => SnippetPosition::Head,
            'priority' => 10,
            'code' => '<script>window.snippet = true;</script>',
            'description' => null,
            'is_active' => true,

            // Off by default so a test that does not care about the admin-skip
            // rule is not silently affected by it when acting as a user.
            'skip_for_admins' => false,
        ];
    }
}
