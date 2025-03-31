<?php

namespace App\Providers;

use App\Services\LocalCsvUploadService;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register LocalCsvUploadService
        $this->app->singleton(LocalCsvUploadService::class, function ($app) {
            return new LocalCsvUploadService(
                $app->make(CsvUploadRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Set the default guard to API
        Auth::shouldUse('api');
    }
}
