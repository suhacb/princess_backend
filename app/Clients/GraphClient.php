<?php

namespace App\Clients;

use App\Contracts\GraphClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GraphClient implements GraphClientContract
{
    private const METADATA_URL = 'https://graph.microsoft.com/v1.0/$metadata';

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function ping(): bool
    {
        try {
            Http::timeout(5)->get(self::METADATA_URL);
            return true;
        } catch (ConnectionException) {
            return false;
        }
    }
}
