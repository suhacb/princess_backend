<?php

namespace App\Providers;

use App\Clients\OllamaClient;
use App\Contracts\OllamaClientContract;
use Illuminate\Support\ServiceProvider;

class OllamaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OllamaClientContract::class, function () {
            return new OllamaClient(
                baseUrl:    config('princess.ollama.base_url'),
                model:      config('princess.ollama.model'),
                modelFast:  config('princess.ollama.model_fast'),
                modelSmart: config('princess.ollama.model_smart'),
                timeout:    config('princess.ollama.timeout'),
            );
        });
    }
}
