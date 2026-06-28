<?php

namespace App\Providers;

use App\Clients\GarageAdminClient;
use App\Contracts\GarageAdminClientContract;
use Illuminate\Support\ServiceProvider;

class GarageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GarageAdminClientContract::class, function () {
            return new GarageAdminClient(
                adminUrl: config('princess.garage.admin_url'),
            );
        });
    }
}
