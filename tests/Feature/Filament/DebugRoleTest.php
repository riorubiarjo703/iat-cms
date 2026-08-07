<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class DebugRoleTest extends TestCase
{
    use ActsAsSuperAdmin;
    use RefreshDatabase;

    public function test_debug(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $target = User::factory()->create();
        $target->assignRole('super_admin');
        $target = $target->fresh();

        $actor = User::factory()->create();
        $custodian = Role::create(['name' => 'role_custodian']);
        $custodian->givePermissionTo(['admin.access', 'roles.manage', 'users.update']);
        $actor->assignRole('role_custodian');
        $this->actingAs($actor);

        fwrite(STDERR, "actor can admin.access: " . var_export($actor->can('admin.access'), true) . "\n");
        fwrite(STDERR, "actor can users.update: " . var_export($actor->can('users.update'), true) . "\n");
        fwrite(STDERR, "actor can roles.manage: " . var_export($actor->can('roles.manage'), true) . "\n");
        fwrite(STDERR, "policy update() direct: " . var_export((new \App\Policies\UserPolicy())->update($actor, $target), true) . "\n");
        fwrite(STDERR, "Gate::forUser update: " . var_export(\Illuminate\Support\Facades\Gate::forUser($actor)->allows('update', $target), true) . "\n");
        fwrite(STDERR, "canEdit: " . var_export(\App\Filament\Resources\Users\UserResource::canEdit($target), true) . "\n");

        $this->withoutExceptionHandling();

        try {
            $test = Livewire::test(EditUser::class, ['record' => $target->getRouteKey()]);
            fwrite(STDERR, "MOUNT OK\n");
            $instance = $test->instance();
            fwrite(STDERR, "instance is null: " . var_export($instance === null, true) . "\n");

            $ref = new \ReflectionObject($test);
            $prop = $ref->getProperty('lastState');
            $prop->setAccessible(true);
            $lastState = $prop->getValue($test);
            $ref2 = new \ReflectionObject($lastState);
            $respProp = $ref2->getProperty('response');
            $respProp->setAccessible(true);
            $response = $respProp->getValue($lastState);
            fwrite(STDERR, "status: " . $response->getStatusCode() . "\n");
            fwrite(STDERR, "headers: " . json_encode($response->headers->all()) . "\n");
            $content = $response->getContent();
            file_put_contents(base_path('debug_403.html'), $content);
            fwrite(STDERR, "wrote content, len=" . strlen($content) . "\n");
        } catch (\Throwable $e) {
            fwrite(STDERR, "MOUNT EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n");
            fwrite(STDERR, $e->getTraceAsString() . "\n");
        }

        $this->assertTrue(true);
    }
}
