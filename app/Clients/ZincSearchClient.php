<?php

namespace App\Clients;

use App\Contracts\ZincSearchClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ZincSearchClient implements ZincSearchClientContract
{
    public function __construct(
        private readonly string $baseUri,
        private readonly string $user,
        private readonly string $password,
    ) {}

    public function ping(): bool
    {
        try {
            return Http::timeout(5)
                ->withBasicAuth($this->user, $this->password)
                ->get("{$this->baseUri}/healthz")
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
