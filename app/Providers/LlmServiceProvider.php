<?php

namespace App\Providers;

use App\Clients\OllamaClient;
use App\Clients\TogetherAiClient;
use App\Services\Llm\LlmRouterService;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OllamaClient::class, function () {
            return new OllamaClient(
                baseUrl: config('princess.ollama.base_url'),
                model:   config('princess.ollama.model'),
                timeout: config('princess.ollama.timeout'),
            );
        });

        $this->app->singleton(TogetherAiClient::class, function () {
            return new TogetherAiClient(
                baseUrl: config('princess.together.base_url'),
                apiKey:  config('princess.together.api_key'),
                model:   config('princess.together.model'),
                timeout: config('princess.together.timeout'),
            );
        });

        $this->app->singleton(LlmRouterService::class, function ($app) {
            return new LlmRouterService(
                providers: [
                    'ollama'   => $app->make(OllamaClient::class),
                    'together' => $app->make(TogetherAiClient::class),
                ],
                tiers: config('princess.llm.tiers'),
            );
        });
    }
}
