<?php

namespace App\Providers;

use App\Clients\GraphClient;
use App\Contracts\GraphClientContract;
use Illuminate\Support\ServiceProvider;

class GraphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GraphClientContract::class, function () {
            return new GraphClient(
                tenantId:     config('princess.m365.tenant_id') ?? '',
                clientId:     config('princess.m365.client_id') ?? '',
                clientSecret: config('princess.m365.client_secret') ?? '',
            );
        });
    }
}
