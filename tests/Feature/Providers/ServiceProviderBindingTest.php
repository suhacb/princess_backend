<?php

namespace Tests\Feature\Providers;

use App\Clients\AuthBackendClient;
use App\Clients\GarageAdminClient;
use App\Clients\GraphClient;
use App\Clients\OllamaClient;
use App\Clients\QdrantClient;
use App\Clients\TogetherAiClient;
use App\Clients\ZincSearchClient;
use App\Contracts\AuthGatewayClientContract;
use App\Contracts\GarageAdminClientContract;
use App\Contracts\GraphClientContract;
use App\Contracts\QdrantClientContract;
use App\Contracts\ZincSearchClientContract;
use App\Services\Llm\LlmRouterService;
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

    public function test_ollama_client_is_registered(): void
    {
        $this->assertInstanceOf(OllamaClient::class, app(OllamaClient::class));
    }

    public function test_together_ai_client_is_registered(): void
    {
        $this->assertInstanceOf(TogetherAiClient::class, app(TogetherAiClient::class));
    }

    public function test_llm_router_service_is_registered(): void
    {
        $this->assertInstanceOf(LlmRouterService::class, app(LlmRouterService::class));
    }

    public function test_qdrant_contract_resolves_to_qdrant_client(): void
    {
        $this->assertInstanceOf(QdrantClient::class, app(QdrantClientContract::class));
    }

    public function test_zinc_search_contract_resolves_to_zinc_search_client(): void
    {
        $this->assertInstanceOf(ZincSearchClient::class, app(ZincSearchClientContract::class));
    }

    public function test_garage_admin_contract_resolves_to_garage_admin_client(): void
    {
        $this->assertInstanceOf(GarageAdminClient::class, app(GarageAdminClientContract::class));
    }

    public function test_all_bindings_are_singletons(): void
    {
        $this->assertSame(app(AuthGatewayClientContract::class), app(AuthGatewayClientContract::class));
        $this->assertSame(app(GraphClientContract::class), app(GraphClientContract::class));
        $this->assertSame(app(OllamaClient::class), app(OllamaClient::class));
        $this->assertSame(app(TogetherAiClient::class), app(TogetherAiClient::class));
        $this->assertSame(app(LlmRouterService::class), app(LlmRouterService::class));
        $this->assertSame(app(QdrantClientContract::class), app(QdrantClientContract::class));
        $this->assertSame(app(ZincSearchClientContract::class), app(ZincSearchClientContract::class));
        $this->assertSame(app(GarageAdminClientContract::class), app(GarageAdminClientContract::class));
    }
}
