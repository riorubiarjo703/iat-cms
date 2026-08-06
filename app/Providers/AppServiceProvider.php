<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @menu('slug') renders a menu by slug; @menuLocation('header') renders
        // whichever menu is assigned to that location. Both exist because the
        // admin offers both — a menu has a directive of its own and can also
        // be assigned to a location.
        Blade::directive('menu', fn (string $expression): string => "<?php echo \\App\\Support\\MenuRenderer::bySlug({$expression}); ?>");
        Blade::directive('menuLocation', fn (string $expression): string => "<?php echo \\App\\Support\\MenuRenderer::byLocation({$expression}); ?>");

        // Laravel discovers App\Policies\<Model>Policy beside App\Models\<Model>.
        // These two models live in the filament-story package, so nothing would be
        // discovered for them and every check would silently fall through to denied.
        Gate::policy(\AjayDhakal\FilamentStory\Models\BlogPost::class, \App\Policies\BlogPostPolicy::class);
        Gate::policy(\AjayDhakal\FilamentStory\Models\BlogCategory::class, \App\Policies\BlogCategoryPolicy::class);

        //
    }
}
