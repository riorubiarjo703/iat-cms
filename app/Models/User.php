<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Exceptions\LastSuperAdminException;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles {
        HasRoles::removeRole as protected removeRoleFromTrait;
        HasRoles::syncRoles as protected syncRolesFromTrait;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Panel access is a permission, not merely authentication.
     *
     * This returned `true` for every authenticated account until roles
     * existed. The migration that shipped alongside this granted super_admin
     * to the three named operator accounts, so their access was unchanged on
     * deploy. The local test login and factory accounts were deliberately
     * left out — they keep their logins but lose panel access; demotion or
     * restoration from here is a deliberate act on the Users screen.
     *
     * Checks the literal string 'admin.access' rather than resolving it by
     * is_system, unlike isLastSuperAdmin() and removeRole() above. That is
     * safe only because PermissionResource's name field is disabled *and*
     * un-dehydrated for is_system rows (admin.access is the only one) — the
     * permission this method names cannot be renamed out from under it. If
     * that guard is ever relaxed, this must resolve by is_system too.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('admin.access');
    }

    /**
     * Guarded at the model rather than in a form: a form check is bypassed by
     * tinker, by a bulk action, and by the Users screen that has not been
     * built yet. This belongs where the change actually happens.
     *
     * Registered in boot() — ahead of parent::boot(), which runs bootTraits()
     * — rather than in booted(). HasRoles::bootHasRoles() registers its own
     * `deleting` listener that detaches every role pivot row for any model
     * without soft deletes (this one included), regardless of whether the
     * delete is later cancelled. A listener added in booted() would run
     * after that detach had already happened, so isLastSuperAdmin() would
     * find the role gone and never throw.
     */
    protected static function boot(): void
    {
        static::deleting(function (User $user): void {
            if ($user->isLastSuperAdmin()) {
                throw LastSuperAdminException::make();
            }
        });

        parent::boot();
    }

    /**
     * The role these lockout guards protect, resolved by is_system rather
     * than the literal name "super_admin". Comparing by name would stop
     * protecting the role the moment somebody renamed it — and renaming is
     * allowed, guarded separately at the form by RoleResource's disabled
     * name field (see PermissionCatalogue's and RoleResource's docblocks).
     * Renaming super_admin here would otherwise make this method, and
     * removeRole() below, resolve nothing — silently disabling the guard —
     * and the next seeder run would then create a *second* all-permissions
     * role via updateOrCreate(['name' => 'super_admin']).
     */
    private static function protectedRole(): ?Role
    {
        return Role::where('is_system', true)->first();
    }

    public function isLastSuperAdmin(): bool
    {
        $protected = self::protectedRole();

        if ($protected === null || ! $this->hasRole($protected)) {
            return false;
        }

        return $protected->users()->count() <= 1;
    }

    /**
     * Spatie's HasRoles::removeRole() is variadic — `...$role`, matched here
     * exactly (vendor/spatie/laravel-permission/src/Traits/HasRoles.php) —
     * so an id, a Role, a name, or a mix of any of those in one call must all
     * be checked before delegating.
     *
     * HasRoles is a trait, not a base class: there is no `parent::removeRole()`
     * to fall back to. The trait's implementation is aliased to
     * removeRoleFromTrait() above so it can still be reached.
     */
    public function removeRole(...$role): static
    {
        $protected = self::protectedRole();
        $roleIds = $this->collectRoles($role);

        if ($protected !== null && in_array($protected->getKey(), $roleIds, true) && $this->isLastSuperAdmin()) {
            throw LastSuperAdminException::make();
        }

        return $this->removeRoleFromTrait(...$role);
    }

    /**
     * With `events_enabled => false` (this app's config/permission.php),
     * HasRoles::syncRoles() calls detachRoles() directly and never reaches
     * removeRole() — so a role-set replacement (a relationship-backed
     * CheckboxList on the future Users screen produces exactly this call)
     * could demote the last super_admin with no guard at all.
     *
     * Flipping events_enabled to true instead was rejected: Spatie's
     * event-enabled path *removes* the current roles before *assigning* the
     * new ones, so syncRoles(['super_admin', 'content_editor']) on the last
     * super admin would throw a false positive — removeRole() sees the
     * assignment has not happened yet and refuses a call that would not
     * actually have left anyone unprotected.
     *
     * So the resulting set is computed first, and the exception is thrown
     * only when the protected role is held now and would be absent after —
     * the same isLastSuperAdmin() check removeRole() makes, on the same
     * before/after shape syncRoles() itself has, just evaluated up front
     * instead of via Spatie's own remove-then-assign sequence.
     */
    public function syncRoles(...$roles): static
    {
        $protected = self::protectedRole();

        if ($protected !== null) {
            $resulting = $this->collectRoles($roles);
            $holdsNow = $this->hasRole($protected);
            $holdsAfter = in_array($protected->getKey(), $resulting, true);

            if ($holdsNow && ! $holdsAfter && $this->isLastSuperAdmin()) {
                throw LastSuperAdminException::make();
            }
        }

        return $this->syncRolesFromTrait(...$roles);
    }
}
