<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // A plain factory user now hits the policies added for this
        // resource and is refused; the resource's own behaviour, not
        // authorization, is what this suite covers.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_user_with_a_hashed_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Laurentius',
                'email' => 'new@storeframe.io',
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new@storeframe.io')->sole();

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@storeframe.io']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'taken@storeframe.io', 'password' => 'secret-password'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_editing_with_blank_password_preserves_current_hash(): void
    {
        $user = User::factory()->create(['email' => 'edit@storeframe.io']);
        $originalHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'edit@storeframe.io',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_editing_with_new_password_updates_hash(): void
    {
        $user = User::factory()->create(['email' => 'update-pw@storeframe.io']);
        $originalHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => 'update-pw@storeframe.io',
                'password' => 'new-secret-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertNotSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('new-secret-password', $user->password));
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test',
                'email' => 'short@storeframe.io',
                'password' => 'short',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }
}
