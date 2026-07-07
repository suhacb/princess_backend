<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DocumentServiceProvider;
use App\Providers\GarageServiceProvider;
use App\Providers\GraphServiceProvider;
use App\Providers\LlmServiceProvider;
use App\Providers\QdrantServiceProvider;
use App\Providers\ZincSearchServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    DocumentServiceProvider::class,
    GarageServiceProvider::class,
    GraphServiceProvider::class,
    LlmServiceProvider::class,
    QdrantServiceProvider::class,
    ZincSearchServiceProvider::class,
];
