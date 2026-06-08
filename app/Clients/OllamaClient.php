<?php

namespace App\Clients;

use App\Contracts\OllamaClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaClient implements OllamaClientContract
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $modelFast,
        private readonly string $modelSmart,
        private readonly int    $timeout,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->get("{$this->baseUrl}/api/tags")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
