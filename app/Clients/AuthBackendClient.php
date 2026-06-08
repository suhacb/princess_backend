<?php

namespace App\Clients;

use App\Contracts\AuthGatewayClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AuthBackendClient implements AuthGatewayClientContract
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int    $port,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->get("{$this->baseUrl}:{$this->port}/api/health")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
