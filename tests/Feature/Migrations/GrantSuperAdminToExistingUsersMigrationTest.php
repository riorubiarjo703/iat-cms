<?php

namespace Tests\Feature\Migrations;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * I2: the migration early-returned whenever the super_admin role was absent,
 * on the assumption that meant a fresh install with nobody to rescue. That
 * assumption is wrong for a restored pre-roles database dump: real user rows
 * exist, but RolesAndPermissionsSeeder has genuinely never run against this
 * database. The migration used to no-op, `migrations` would still record it
 * as run, and there was no retry — every operator locked out permanently.
 */
class GrantSuperAdminToExistingUsersMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATORS = [
        'admin@iat-cms.test',
        'rio.rubiarjo@iat.id',
        'klein.burnice@example.org',
    ];

    /**
     * Loads the migration's anonymous class instance the same way Laravel's
     * migrator does, without running the whole migrate cycle — RefreshDatabase
     * has already migrated everything once for this test.
     */
    private function migration(): object
    {
        return require database_path('migrations/2026_08_06_152146_grant_super_admin_to_existing_users.php');
    }

    /**
     * Simulates a pre-roles dump: real users, but an empty roles table —
     * distinct from a fresh install, which has no users either.
     */
    private function wipeRolesAndPermissions(): void
    {
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_seeds_the_roles_itself_when_they_are_missing_but_users_exist(): void
    {
        User::factory()->create(['email' => 'admin@iat-cms.test']);
        $this->wipeRolesAndPermissions();

        $this->assertNull(Role::query()->where('name', 'super_admin')->first());

        $this->migration()->up();

        $this->assertNotNull(Role::query()->where('name', 'super_admin')->first());
    }

    public function test_it_grants_super_admin_to_the_named_operators_after_self_seeding(): void
    {
        foreach (self::OPERATORS as $email) {
            User::factory()->create(['email' => $email]);
        }
        $this->wipeRolesAndPermissions();

        $this->migration()->up();

        foreach (self::OPERATORS as $email) {
            $user = User::query()->where('email', $email)->first();

            $this->assertTrue($user->fresh()->hasRole('super_admin'), "{$email} should hold super_admin.");
        }
    }

    /** A genuinely fresh install has no users either — nothing to self-seed for. */
    public function test_a_fresh_install_with_no_users_does_nothing(): void
    {
        $this->wipeRolesAndPermissions();

        $this->assertSame(0, User::query()->count());

        $this->migration()->up();

        $this->assertNull(Role::query()->where('name', 'super_admin')->first());
    }

    /** The ordinary path — the seeder has already run — is unaffected. */
    public function test_it_still_grants_super_admin_when_the_role_already_exists(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create(['email' => 'admin@iat-cms.test']);

        $this->migration()->up();

        $this->assertTrue($operator->fresh()->hasRole('super_admin'));
    }
}
