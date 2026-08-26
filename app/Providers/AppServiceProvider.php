<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // As listagens montam props do Inertia percorrendo coleções, onde um
        // relacionamento não carregado vira uma consulta por linha.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
