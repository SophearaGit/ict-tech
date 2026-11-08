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
        // The shop menu in the page layout lists the categories that are switched ON.
        View::composer('layouts.app', function ($view) {
            $view->with('shopCategories', app(ShopCategories::class)->active());
        });
    }
}
