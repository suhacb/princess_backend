<?php

namespace App\Providers;

use App\Clients\AuthBackendClient;
use App\Contracts\AuthGatewayClientContract;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthGatewayClientContract::class, function () {
            return new AuthBackendClient(
                baseUrl: config('princess.auth.url_backend'),
                port:    (int) config('princess.auth.port_backend'),
            );
        });
    }
}
