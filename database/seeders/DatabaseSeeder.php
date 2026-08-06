<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // A predictable throwaway login is fine locally, but must never exist
        // in a deployed environment — User::canAccessPanel() grants any
        // authenticated user full admin access.
        //
        // firstOrCreate rather than create: the email is unique, so a second
        // `db:seed` threw here and took every seeder below it down with it —
        // which is how the chain came to be one line long.
        if (app()->environment('local')) {
            User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                User::factory()->raw(['email' => 'test@example.com', 'name' => 'Test User']),
            );
        }

        // Order is a dependency chain, not a preference.
        //
        // HomepageSeeder builds Site Settings, the shared District/Facility
        // records and the homepage. NavigationTreeSeeder then creates the draft
        // page shells the menu points at. The content seeders fill those shells
        // in, and each one warns and returns if its page is missing — so
        // running them before the tree produces a silent no-op, not an error.
        $this->call([
            HomepageSeeder::class,
            NavigationTreeSeeder::class,
            ProfilePageSeeder::class,
            CompanyPagesSeeder::class,
            DistrictFacilitiesPageSeeder::class,
            ContactPageSeeder::class,
        ]);
    }
}
