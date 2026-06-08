<?php

namespace App\Providers;

use App\Clients\ZincSearchClient;
use App\Contracts\ZincSearchClientContract;
use Illuminate\Support\ServiceProvider;

class ZincSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZincSearchClientContract::class, function () {
            return new ZincSearchClient(
                baseUri:  config('princess.zinc.base_uri'),
                user:     config('princess.zinc.user'),
                password: config('princess.zinc.password'),
            );
        });
    }
}
