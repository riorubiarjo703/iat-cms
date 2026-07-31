<?php

namespace Tests\Feature\Filament;

use App\Enums\StatFormat;
use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use App\Filament\Resources\DistrictPlaces\Pages\CreateDistrictPlace;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\Facilities\Pages\CreateFacility;
use App\Filament\Resources\PublicMenuItems\Pages\CreatePublicMenuItem;
use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use App\Filament\Resources\Stats\Pages\CreateStat;
use App\Filament\Resources\Stats\StatResource;
use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\PublicMenuItem;
use App\Models\Stat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderedResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public static function resourceProvider(): array
    {
        return [
            'district places' => [DistrictPlaceResource::class],
            'facilities' => [FacilityResource::class],
            'stats' => [StatResource::class],
            'public menu items' => [PublicMenuItemResource::class],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_the_index_page_renders(string $resource): void
    {
        $this->get($resource::getUrl('index'))->assertSuccessful();
    }

    #[DataProvider('resourceProvider')]
    public function test_the_create_page_renders(string $resource): void
    {
        $this->get($resource::getUrl('create'))->assertSuccessful();
    }

    public function test_it_creates_a_district_place_with_translations(): void
    {
        Livewire::test(CreateDistrictPlace::class)
            ->fillForm([
                'title' => ['en' => 'The towers', 'id' => 'Menara'],
                'caption' => ['en' => 'Grade A office'],
                'sort' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $place = DistrictPlace::query()->sole();

        $this->assertSame('The towers', $place->t('title', 'en'));
        $this->assertSame('Menara', $place->t('title', 'id'));
        $this->assertSame('The towers', $place->t('title', 'cn'));
    }

    public function test_english_title_is_required(): void
    {
        Livewire::test(CreateDistrictPlace::class)
            ->fillForm(['title' => ['en' => null, 'id' => 'Menara']])
            ->call('create')
            ->assertHasFormErrors(['title.en' => 'required']);
    }

    public function test_it_creates_a_facility_with_translations(): void
    {
        Livewire::test(CreateFacility::class)
            ->fillForm([
                'title' => ['en' => 'Fitness centre', 'id' => 'Pusat kebugaran'],
                'body' => ['en' => 'Open 24 hours with city views.'],
                'sort' => 2,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $facility = Facility::query()->sole();

        $this->assertSame('Fitness centre', $facility->t('title', 'en'));
        $this->assertSame('Pusat kebugaran', $facility->t('title', 'id'));
        $this->assertSame('Open 24 hours with city views.', $facility->t('body', 'en'));
        $this->assertSame('Open 24 hours with city views.', $facility->t('body', 'id'));
    }

    public function test_english_facility_title_is_required(): void
    {
        Livewire::test(CreateFacility::class)
            ->fillForm(['title' => ['en' => null, 'id' => 'Pusat kebugaran']])
            ->call('create')
            ->assertHasFormErrors(['title.en' => 'required']);
    }

    public function test_it_creates_a_stat_with_a_format_and_suffix(): void
    {
        Livewire::test(CreateStat::class)
            ->fillForm([
                'label' => ['en' => 'District security & response'],
                'value' => 24,
                'suffix' => '/7',
                'format' => StatFormat::Thousands->value,
                'sort' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stat = Stat::query()->sole();

        $this->assertSame(StatFormat::Thousands, $stat->format);
        $this->assertSame('/7', $stat->suffix);
    }

    public function test_it_creates_a_cta_menu_item(): void
    {
        Livewire::test(CreatePublicMenuItem::class)
            ->fillForm([
                'label' => ['en' => 'Leasing enquiry'],
                'url' => '#contact',
                'target' => '_self',
                'sort' => 5,
                'is_active' => true,
                'is_cta' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(PublicMenuItem::query()->sole()->is_cta);
    }

    #[DataProvider('resourceProvider')]
    public function test_the_table_is_reorderable_by_sort(string $resource): void
    {
        $listPage = $resource::getPages()['index']->getPage();

        $this->assertSame('sort', $resource::table(
            app(\Filament\Tables\Table::class, ['livewire' => Livewire::test($listPage)->instance()])
        )->getReorderColumn());
    }
}
