<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\Stats\StatResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * C4: DistrictPlaceResource, FacilityResource and StatResource had no policy
 * at all, so every ability fell through to Filament's allow-by-default — a
 * content_editor could open any of the three by URL and bulk delete the
 * homepage's district, facility and stat records. They are homepage
 * structure the administrator owns, not content, so — unlike Pages — an
 * editor is refused outright rather than merely denied create/delete.
 */
class HomepageStructureAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function resourceProvider(): array
    {
        return [
            'district places' => [DistrictPlaceResource::class],
            'facilities' => [FacilityResource::class],
            'stats' => [StatResource::class],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_a_content_editor_is_forbidden_from_the_index(string $resource): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');

        $this->actingAs($editor)
            ->get($resource::getUrl('index'))
            ->assertForbidden();
    }

    #[DataProvider('resourceProvider')]
    public function test_a_super_admin_reaches_the_index(string $resource): void
    {
        $this->actingAsSuperAdmin()
            ->get($resource::getUrl('index'))
            ->assertSuccessful();
    }

    #[DataProvider('resourceProvider')]
    public function test_a_content_editor_cannot_bulk_delete(string $resource): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor);

        $this->assertFalse($resource::canDeleteAny());
    }

    #[DataProvider('resourceProvider')]
    public function test_a_content_editor_cannot_reorder(string $resource): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor);

        $this->assertFalse($resource::canReorder());
    }

    #[DataProvider('resourceProvider')]
    public function test_a_super_admin_can_bulk_delete_and_reorder(string $resource): void
    {
        $this->actingAsSuperAdmin();

        $this->assertTrue($resource::canDeleteAny());
        $this->assertTrue($resource::canReorder());
    }
}
