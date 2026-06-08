<?php

namespace Tests\Feature\Providers;

use App\Clients\AuthBackendClient;
use App\Clients\GraphClient;
use App\Clients\OllamaClient;
use App\Clients\QdrantClient;
use App\Clients\ZincSearchClient;
use App\Contracts\AuthGatewayClientContract;
use App\Contracts\GraphClientContract;
use App\Contracts\OllamaClientContract;
use App\Contracts\QdrantClientContract;
use App\Contracts\ZincSearchClientContract;
use Tests\TestCase;

class ServiceProviderBindingTest extends TestCase
{
    public function test_auth_gateway_contract_resolves_to_auth_backend_client(): void
    {
        $this->assertInstanceOf(AuthBackendClient::class, app(AuthGatewayClientContract::class));
    }

    public function test_graph_contract_resolves_to_graph_client(): void
    {
        $this->assertInstanceOf(GraphClient::class, app(GraphClientContract::class));
    }

    public function test_ollama_contract_resolves_to_ollama_client(): void
    {
        $this->assertInstanceOf(OllamaClient::class, app(OllamaClientContract::class));
    }

    public function test_qdrant_contract_resolves_to_qdrant_client(): void
    {
        $this->assertInstanceOf(QdrantClient::class, app(QdrantClientContract::class));
    }

    public function test_zinc_search_contract_resolves_to_zinc_search_client(): void
    {
        $this->assertInstanceOf(ZincSearchClient::class, app(ZincSearchClientContract::class));
    }

    public function test_all_bindings_are_singletons(): void
    {
        $this->assertSame(app(AuthGatewayClientContract::class), app(AuthGatewayClientContract::class));
        $this->assertSame(app(GraphClientContract::class), app(GraphClientContract::class));
        $this->assertSame(app(OllamaClientContract::class), app(OllamaClientContract::class));
        $this->assertSame(app(QdrantClientContract::class), app(QdrantClientContract::class));
        $this->assertSame(app(ZincSearchClientContract::class), app(ZincSearchClientContract::class));
    }
}
