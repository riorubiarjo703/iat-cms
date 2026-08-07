<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The operator accounts predating roles would otherwise be locked out the
 * moment canAccessPanel() started checking a permission. They get super_admin,
 * which is exactly the access they already had, and can be demoted from the
 * Users screen afterwards.
 *
 * Named accounts rather than "every user", deliberately. The installation also
 * carries test@example.com — the local convenience login whose password is
 * published in DatabaseSeeder — and two factory accounts. Granting super_admin
 * to a known credential would hand full administrative access to anyone who
 * has read the seeder, which is the opposite of what this feature is for.
 * Those three keep their logins and lose panel access, which is the correct
 * outcome for a throwaway.
 *
 * A fresh install matches none of these and correctly does nothing. A
 * restored pre-roles database dump is different from a fresh install even
 * though both find the roles table empty: it has real user rows, so up()
 * seeds the roles and permissions itself rather than assuming
 * RolesAndPermissionsSeeder already ran — see the self-seeding branch below.
 */
return new class extends Migration
{
    /** The real operator accounts on this installation. */
    private const OPERATORS = [
        'admin@iat-cms.test',
        'rio.rubiarjo@iat.id',
        'klein.burnice@example.org',
    ];

    public function up(): void
    {
        $role = Role::query()->where('name', 'super_admin')->first();

        if ($role === null) {
            // Two situations produce an absent role, and only one of them is
            // safe to no-op on. A genuinely fresh install has no users
            // either — nobody to rescue, and DatabaseSeeder runs
            // RolesAndPermissionsSeeder itself on that path. But a pre-roles
            // database dump restored here has real user rows and an empty
            // roles table: without self-seeding, this migration would no-op,
            // `migrations` would still record it as run, and there would be
            // no retry — every operator locked out permanently, with no way
            // back short of a manual `db:seed`. Self-seeding makes this
            // migration correct regardless of which situation it finds.
            if (User::query()->doesntExist()) {
                return;
            }

            (new RolesAndPermissionsSeeder)->run();

            $role = Role::query()->where('name', 'super_admin')->first();
        }

        User::query()
            ->whereIn('email', self::OPERATORS)
            ->each(function (User $user) use ($role): void {
                if ($user->roles()->count() === 0) {
                    $user->assignRole($role);
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: which users held super_admin before this ran
        // is not recorded, and guessing would either strip access from someone
        // who should keep it or leave it with someone who should not.
    }
};
