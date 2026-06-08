<?php

namespace App\Providers;

use App\Clients\QdrantClient;
use App\Contracts\QdrantClientContract;
use Illuminate\Support\ServiceProvider;

class QdrantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QdrantClientContract::class, function () {
            return new QdrantClient(
                baseUrl: config('princess.qdrant.base_url'),
            );
        });
    }
}
