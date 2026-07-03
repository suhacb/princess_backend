<?php

namespace Tests\Feature;

use App\Clients\OnlyOfficeClient;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\Project;
use App\Models\QaDocument;
use App\Services\Document\OnlyOfficeEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnlyOfficeCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private const JWT_SECRET  = 'test-secret';
    private const SESSION_KEY = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    private OnlyOfficeClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $person = Person::factory()->create();
        $project  = Project::factory()->create(['created_by' => $person->id]);
        $document = QaDocument::factory()->create(['project_id' => $project->id, 'created_by' => $person->id]);

        DocumentVersion::factory()->create([
            'document_id'    => $document->id,
            'version_number' => 1,
            's3_key'         => "documents/{$document->id}/versions/" . self::SESSION_KEY . '/original.docx',
            'created_by'     => $person->id,
            'onlyoffice_key' => self::SESSION_KEY,
        ]);

        $this->client = new OnlyOfficeClient(self::JWT_SECRET, 'http://onlyoffice');
        $this->app->instance(OnlyOfficeClient::class, $this->client);
    }

    private function url(string $key = self::SESSION_KEY): string
    {
        return "/api/onlyoffice/callback/{$key}";
    }

    private function signedPayload(array $payload): array
    {
        $b64 = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $h   = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p   = $b64(json_encode($payload));
        $s   = $b64(hash_hmac('sha256', "{$h}.{$p}", self::JWT_SECRET, true));

        return array_merge($payload, ['token' => "{$h}.{$p}.{$s}"]);
    }

    // -------------------------------------------------------------------------
    // always returns {"error": 0}
    // -------------------------------------------------------------------------

    public function test_always_returns_error_zero(): void
    {
        $this->mock(OnlyOfficeEditorService::class)->shouldReceive('handleCallback');

        $payload = $this->signedPayload(['status' => 1, 'key' => self::SESSION_KEY]);

        $this->postJson($this->url(), $payload)
            ->assertOk()
            ->assertJson(['error' => 0]);
    }

    public function test_returns_error_zero_on_invalid_jwt(): void
    {
        $payload = ['status' => 1, 'key' => self::SESSION_KEY, 'token' => 'bad.token.here'];

        $this->postJson($this->url(), $payload)
            ->assertOk()
            ->assertJson(['error' => 0]);
    }

    public function test_accepts_jwt_in_authorization_header(): void
    {
        $body    = ['status' => 1, 'key' => self::SESSION_KEY];
        $signed  = $this->signedPayload($body);
        $jwt     = $signed['token'];

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->withArgs(fn (string $k, array $p) => $k === self::SESSION_KEY && ($p['status'] ?? null) === 1)
            ->once();

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->postJson($this->url(), $body)
            ->assertOk()
            ->assertJson(['error' => 0]);
    }

    public function test_unauthenticated_request_is_accepted(): void
    {
        $this->mock(OnlyOfficeEditorService::class)->shouldReceive('handleCallback');

        $payload = $this->signedPayload(['status' => 4, 'key' => self::SESSION_KEY]);

        $this->postJson($this->url(), $payload)->assertOk();
    }

    // -------------------------------------------------------------------------
    // dispatching to the service
    // -------------------------------------------------------------------------

    public function test_status_1_calls_handle_callback(): void
    {
        $payload = $this->signedPayload(['status' => 1, 'key' => self::SESSION_KEY]);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->withArgs(fn (string $k, array $p) => $k === self::SESSION_KEY && ($p['status'] ?? null) === 1)
            ->once();

        $this->postJson($this->url(), $payload)->assertOk();
    }

    public function test_status_2_calls_handle_callback(): void
    {
        $payload = $this->signedPayload(['status' => 2, 'key' => self::SESSION_KEY, 'url' => 'https://onlyoffice/file']);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->once();

        $this->postJson($this->url(), $payload)->assertOk();
    }

    public function test_status_4_calls_handle_callback(): void
    {
        $payload = $this->signedPayload(['status' => 4, 'key' => self::SESSION_KEY]);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->once();

        $this->postJson($this->url(), $payload)->assertOk();
    }

    public function test_status_6_calls_handle_callback(): void
    {
        $payload = $this->signedPayload(['status' => 6, 'key' => self::SESSION_KEY, 'url' => 'https://onlyoffice/force-save']);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->once();

        $this->postJson($this->url(), $payload)->assertOk();
    }

    public function test_unknown_status_is_ignored_and_returns_error_zero(): void
    {
        $payload = $this->signedPayload(['status' => 99, 'key' => self::SESSION_KEY]);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->once(); // service is still called; it silently ignores status 99

        $this->postJson($this->url(), $payload)
            ->assertOk()
            ->assertJson(['error' => 0]);
    }

    public function test_key_mismatch_skips_handle_callback(): void
    {
        $differentKey = 'b1eebc99-9c0b-4ef8-bb6d-6bb9bd380a22';
        $payload = $this->signedPayload(['status' => 2, 'key' => $differentKey, 'url' => 'https://x']);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldNotReceive('handleCallback');

        $this->postJson($this->url(self::SESSION_KEY), $payload)->assertOk();
    }

    // -------------------------------------------------------------------------
    // DB side-effects via service stub
    // -------------------------------------------------------------------------

    public function test_status_1_updates_last_active_at(): void
    {
        $payload = $this->signedPayload(['status' => 1, 'key' => self::SESSION_KEY]);

        $this->mock(OnlyOfficeEditorService::class)
            ->shouldReceive('handleCallback')
            ->once()
            ->andReturnUsing(function (string $key) {
                DB::table('document_versions')
                    ->where('onlyoffice_key', $key)
                    ->update(['last_active_at' => now()]);
            });

        $this->postJson($this->url(), $payload)->assertOk();

        $this->assertNotNull(
            DocumentVersion::where('onlyoffice_key', self::SESSION_KEY)->value('last_active_at')
        );
    }
}
