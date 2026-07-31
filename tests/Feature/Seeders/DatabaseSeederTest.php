<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake(['scbd.com/*' => Http::response('image-bytes', 200)]);
    }

    /**
     * The suite runs with APP_ENV=testing (see phpunit.xml), which is the
     * same "not local" position a deployed environment is in. If the
     * seeder ever creates the known test@example.com/password login here,
     * it would create it in production too.
     */
    public function test_it_does_not_create_the_test_user_outside_local_environment(): void
    {
        $this->assertFalse(app()->environment('local'), 'This test is only meaningful when the suite is not running as "local".');

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(
            User::query()->where('email', 'test@example.com')->exists(),
            'db:seed must never create the published test@example.com/password login outside local.'
        );
    }

    /**
     * Proves the guard is an environment check, not a permanent removal of
     * the convenience login: local development still gets it.
     */
    public function test_it_creates_the_test_user_in_the_local_environment(): void
    {
        $originalEnv = app()['env'];
        app()['env'] = 'local';

        try {
            $this->assertTrue(app()->environment('local'));

            $this->seed(DatabaseSeeder::class);

            $this->assertTrue(
                User::query()->where('email', 'test@example.com')->exists(),
                'The local-only test user should still be created when running as local.'
            );
        } finally {
            app()['env'] = $originalEnv;
        }
    }
}
