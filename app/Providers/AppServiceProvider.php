<?php

namespace App\Providers;

use App\Listeners\LogAiRequests;
use App\Models\Item;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogAiRequests::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(LogAiRequests::class);

        View::composer('components.layout', function ($view) {
            $view->with('reviewCount', Item::query()->awaitingReview()->count());
        });
    }
}
