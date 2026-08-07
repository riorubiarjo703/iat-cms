<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ChecksPagePermission;
use App\Models\Menu;
use App\Support\MenuLocations;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * The menus index: which menu is shown where, and every menu that exists.
 *
 * Holds no rendering knowledge — locations come from MenuLocations, counts and
 * trees from the models.
 */
class NavigationMenusPage extends Page
{
    use ChecksPagePermission;

    public static function permission(): string
    {
        return 'menus.manage';
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $title = 'Navigation Menus';

    protected static ?string $slug = 'navigation-menus';

    protected string $view = 'filament.pages.navigation-menus';

    public static function getNavigationLabel(): string
    {
        return 'Navigation Menus';
    }

    public function getSubheading(): ?string
    {
        return "Create and organize your site's navigation structure";
    }

    /** @return Collection<int, Menu> */
    public function getMenus(): Collection
    {
        return Menu::query()
            ->withCount('items')
            ->orderBy('name')
            ->get();
    }

    /**
     * Locations with whatever is currently assigned. The item count shown is
     * the assigned menu's, so an unassigned location reports nothing rather
     * than zero.
     *
     * @return array<string, array{label: string, description: string, icon: string, menu: Menu|null, items: int|null}>
     */
    public function getLocations(): array
    {
        $assigned = Menu::query()->withCount('items')->whereNotNull('location')->get()->keyBy('location');

        $rows = [];

        foreach (MenuLocations::all() as $key => $meta) {
            $menu = $assigned->get($key);

            $rows[$key] = $meta + [
                'menu' => $menu,
                'items' => $menu?->items_count,
            ];
        }

        return $rows;
    }

    /** @return array<int, array{value: string, label: string}> */
    public function getAssignableMenus(): array
    {
        return $this->getMenus()
            ->map(fn (Menu $menu): array => ['value' => (string) $menu->id, 'label' => $menu->name])
            ->all();
    }

    public function assignLocation(string $location, ?string $menuId): void
    {
        if (! MenuLocations::exists($location)) {
            return;
        }

        if (blank($menuId)) {
            Menu::query()->where('location', $location)->update(['location' => null]);
        } else {
            Menu::query()->whereKey($menuId)->first()?->assignLocation($location);
        }

        Notification::make()->title('Saved')->success()->send();
    }

    public function deleteMenu(string $menuId): void
    {
        $menu = Menu::query()->whereKey($menuId)->first();

        if ($menu === null) {
            return;
        }

        $name = $menu->name;
        // Items and their children cascade at the database level.
        $menu->delete();

        Notification::make()->title("Deleted “{$name}”")->success()->send();
    }

    public function newMenuAction(): Action
    {
        return Action::make('newMenu')
            ->label('New Menu')
            ->icon('heroicon-o-plus')
            ->schema([
                TextInput::make('name')
                    ->label('Menu name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Main Navigation'),
            ])
            ->action(function (array $data): void {
                $menu = Menu::create(['name' => $data['name']]);

                $this->redirect(EditMenuPage::getUrl(['record' => $menu->getKey()]));
            });
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [$this->newMenuAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }
}
