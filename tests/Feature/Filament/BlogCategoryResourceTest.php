<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\EditBlogCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogCategoryResourceTest extends TestCase
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
        $this->get(BlogCategoryResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_category_and_the_model_generates_the_slug(): void
    {
        Livewire::test(CreateBlogCategory::class)
            ->fillForm(['name' => 'District News'])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = BlogCategory::query()->sole();

        $this->assertSame('District News', $category->name);
        $this->assertNotEmpty($category->slug);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateBlogCategory::class)
            ->fillForm(['name' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_it_edits_an_existing_category(): void
    {
        $category = BlogCategory::create(['name' => 'Original Name']);
        $originalSlug = $category->slug;

        Livewire::test(EditBlogCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertSame('Updated Name', $category->name);
        $this->assertSame($originalSlug, $category->slug);
    }
}
