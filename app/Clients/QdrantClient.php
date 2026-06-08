<?php

namespace App\Clients;

use App\Contracts\QdrantClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class QdrantClient implements QdrantClientContract
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->get("{$this->baseUrl}/healthz")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
