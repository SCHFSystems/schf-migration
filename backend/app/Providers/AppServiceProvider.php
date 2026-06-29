<?php

namespace App\Providers;

use App\Normalization\MappingProfileRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MappingProfileRegistry::class);
    }

    public function boot(): void
    {
        // No runtime boot hooks required for synthetic mode.
    }
}
