<?php

namespace App\Providers;

use App\Support\ShopCategories;
use Illuminate\Support\Facades\View;
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
        // The page layout needs the categories that are switched ON (the shop menu) and the icon of EVERY
        // category (window.CATEGORY_ICONS, read by the shop and cart scripts).
        View::composer('layouts.app', function ($view) {
            $categories = app(ShopCategories::class);

            $view->with('shopCategories', $categories->active())
                ->with('shopCategoryIcons', $categories->icons());
        });
    }
}
