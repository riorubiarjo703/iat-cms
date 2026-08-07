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
     * db:seed runs DatabaseSeeder, which suppresses model events. Anything the
     * seeded content relies on a model event for silently breaks only on this
     * path — the individual seeder's own tests would still pass.
     */
    public function test_seeding_through_the_database_seeder_produces_a_working_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull(\App\Models\Menu::assignedTo(\App\Support\MenuLocations::HEADER));
        $this->assertNotNull(\App\Models\Page::homepage());

        $this->get('/')->assertSuccessful();
    }

    /**
     * The chain used to be one line — `$this->call(HomepageSeeder::class)` —
     * while five page seeders sat in the directory, uncalled. A fresh seed
     * produced a homepage and nothing behind the navigation.
     */
    public function test_it_seeds_the_pages_behind_the_navigation_not_only_the_homepage(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['profile', 'milestone', 'organisation-structure', 'awards-certification', 'district-facilities'] as $slug) {
            $page = \App\Models\Page::query()->where('slug', $slug)->first();

            $this->assertNotNull($page, "db:seed should create the \"{$slug}\" page.");
            $this->assertNotEmpty(
                $page->builder_payload,
                "The \"{$slug}\" page should have content, not just a draft shell."
            );
        }
    }

    /**
     * NavigationTreeSeeder's entry for Contact Us is `['Contact Us', '#contact']`
     * — a homepage anchor with no slug — so no shell is ever created for it.
     * ContactPageSeeder warned and returned on that, which meant it could never
     * apply its content once, however often it ran.
     */
    public function test_it_seeds_the_contact_page_which_has_no_navigation_shell(): void
    {
        $this->seed(DatabaseSeeder::class);

        $page = \App\Models\Page::query()->where('slug', 'contact-us')->first();

        $this->assertNotNull($page, 'db:seed should create the contact-us page.');
        $this->assertSame(\App\Models\Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->builder_payload);
    }

    /**
     * The test user's email is unique, so an unconditional create threw on the
     * second run and aborted every seeder queued behind it. Re-seeding is
     * routine while content is being iterated, so it has to survive it.
     */
    public function test_seeding_twice_in_the_local_environment_does_not_fail(): void
    {
        $originalEnv = app()['env'];
        app()['env'] = 'local';

        try {
            $this->seed(DatabaseSeeder::class);
            $this->seed(DatabaseSeeder::class);

            $this->assertSame(
                1,
                User::query()->where('email', 'test@example.com')->count(),
                'Re-seeding should not duplicate the local test user.'
            );
            $this->assertNotNull(\App\Models\Page::query()->where('slug', 'contact-us')->first());
        } finally {
            app()['env'] = $originalEnv;
        }
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

    /**
     * I2: User::canAccessPanel() now checks a permission, so `migrate --seed`
     * on a fresh clone used to seed a panel nobody could enter — the local
     * convenience login had no role at all. RolesAndPermissionsSeeder runs
     * earlier in the same chain (test_it_creates_the_test_user_in_the_local_environment
     * proves the user exists at all), so by the time this assigns the role
     * the role already exists to assign.
     */
    public function test_the_test_user_holds_super_admin_in_the_local_environment(): void
    {
        $originalEnv = app()['env'];
        app()['env'] = 'local';

        try {
            $this->seed(DatabaseSeeder::class);

            $testUser = User::query()->where('email', 'test@example.com')->first();

            $this->assertNotNull($testUser);
            $this->assertTrue($testUser->hasRole('super_admin'));
        } finally {
            app()['env'] = $originalEnv;
        }
    }

    /** Re-seeding must not throw trying to assign a role the user already holds. */
    public function test_seeding_twice_in_the_local_environment_keeps_the_test_user_a_super_admin(): void
    {
        $originalEnv = app()['env'];
        app()['env'] = 'local';

        try {
            $this->seed(DatabaseSeeder::class);
            $this->seed(DatabaseSeeder::class);

            $testUser = User::query()->where('email', 'test@example.com')->first();

            $this->assertTrue($testUser->hasRole('super_admin'));
        } finally {
            app()['env'] = $originalEnv;
        }
    }
}
