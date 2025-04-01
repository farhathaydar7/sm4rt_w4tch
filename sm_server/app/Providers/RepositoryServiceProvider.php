<?php

namespace App\Providers;

use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\CsvUploadRepository;
use App\Repositories\ActivityMetricRepository;
use App\Repositories\PredictionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(CsvUploadRepositoryInterface::class, CsvUploadRepository::class);
        $this->app->bind(ActivityMetricRepositoryInterface::class, ActivityMetricRepository::class);
        $this->app->bind(PredictionRepositoryInterface::class, PredictionRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
