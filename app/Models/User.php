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

    public function isLastSuperAdmin(): bool
    {
        if (! $this->hasRole('super_admin')) {
            return false;
        }

        return Role::findByName('super_admin')->users()->count() <= 1;
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
        $superAdminId = Role::where('name', 'super_admin')->value('id');
        $roleIds = $this->collectRoles($role);

        if ($superAdminId !== null && in_array($superAdminId, $roleIds, true) && $this->isLastSuperAdmin()) {
            throw LastSuperAdminException::make();
        }

        return $this->removeRoleFromTrait(...$role);
    }
}
