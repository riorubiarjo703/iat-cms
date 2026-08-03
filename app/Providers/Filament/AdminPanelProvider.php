<?php

namespace App\Providers\Filament;

use AjayDhakal\FilamentStory\FilamentStoryPlugin;
use App\Filament\Navigation\AdminNavigation;
use CybertronianKelvin\Graper\GraperPlugin;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;
use Slimani\MediaManager\MediaManagerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('superduper')
            ->login()
            // Gives the sidebar user menu a Change Password destination.
            ->profile(isSimple: false)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // Branding comes from Site Settings so a logo swap needs no deploy.
            // Resolved lazily: the panel is constructed before the database is
            // necessarily reachable, and singleton() would query at boot.
            ->brandName(fn (): string => \App\Models\SiteSetting::singleton()->site_name ?: config('app.name'))
            ->brandLogo(fn (): ?string => ($logo = \App\Models\SiteSetting::singleton()->logo)
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo)
                : null)
            ->brandLogoHeight('1.75rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigation(fn (NavigationBuilder $builder) => AdminNavigation::build($builder))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->plugin(FilamentStoryPlugin::make())
            ->plugin(GraperPlugin::make())
            ->plugin(TopbarMenuPlugin::make())
            ->plugin(MediaManagerPlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->assets([
                \Filament\Support\Assets\Css::make('sidebar-user', resource_path('css/filament/admin/sidebar-user.css')),
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.sidebar.brand'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::TOPBAR_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.topbar.actions'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.sidebar.user-card'),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
