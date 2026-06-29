<?php

namespace Tests\Unit\Clients;

use App\Clients\OnlyOfficeClient;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\QaDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OnlyOfficeClientTest extends TestCase
{
    private OnlyOfficeClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new OnlyOfficeClient(
            jwtSecret: 'test-secret',
            serverUrl: 'http://onlyoffice',
        );
    }

    // -------------------------------------------------------------------------
    // generateEditorConfig
    // -------------------------------------------------------------------------

    public function test_generate_editor_config_returns_required_fields(): void
    {
        [$document, $version, $person] = $this->makeModels();

        $config = $this->client->generateEditorConfig(
            document: $document,
            version: $version,
            user: $person,
            callbackUrl: 'https://backend/api/onlyoffice/callback/uuid',
            fileUrl: 'https://s3.example.com/file.docx',
        );

        $this->assertArrayHasKey('document', $config);
        $this->assertArrayHasKey('editorConfig', $config);
        $this->assertArrayHasKey('token', $config);
        $this->assertSame('uuid-key', $config['document']['key']);
        $this->assertSame('Test Document', $config['document']['title']);
        $this->assertSame('https://backend/api/onlyoffice/callback/uuid', $config['editorConfig']['callbackUrl']);
    }

    public function test_generate_editor_config_token_is_valid_jwt(): void
    {
        [$document, $version, $person] = $this->makeModels();

        $config = $this->client->generateEditorConfig($document, $version, $person, 'https://cb', 'https://url');
        $token  = $config['token'];

        $this->assertSame(3, substr_count($token, '.') + 1);

        // Re-parse the JWT and confirm the payload matches the config (minus the token field)
        $parts   = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertSame('uuid-key', $payload['document']['key']);
        $this->assertSame('https://cb', $payload['editorConfig']['callbackUrl']);
    }

    public function test_generate_editor_config_accepts_null_file_url(): void
    {
        [$document, $version, $person] = $this->makeModels();

        $config = $this->client->generateEditorConfig($document, $version, $person, 'https://cb', null);

        $this->assertNull($config['document']['url']);
    }

    public function test_generate_editor_config_embeds_user_info(): void
    {
        [$document, $version, $person] = $this->makeModels();

        $config = $this->client->generateEditorConfig($document, $version, $person, 'https://cb', null);

        $this->assertSame('42', $config['editorConfig']['user']['id']);
        $this->assertSame('Alice', $config['editorConfig']['user']['name']);
    }

    // -------------------------------------------------------------------------
    // parseCallback
    // -------------------------------------------------------------------------

    public function test_parse_callback_returns_dto_for_valid_token(): void
    {
        $payload  = ['status' => 2, 'key' => 'uuid-key', 'url' => 'https://onlyoffice/file'];
        $token    = $this->signPayload($payload);

        $dto = $this->client->parseCallback($payload, $token);

        $this->assertSame(2, $dto->status);
        $this->assertSame('uuid-key', $dto->key);
        $this->assertSame('https://onlyoffice/file', $dto->url);
    }

    public function test_parse_callback_returns_dto_without_url(): void
    {
        $payload = ['status' => 1, 'key' => 'uuid-key'];
        $token   = $this->signPayload($payload);

        $dto = $this->client->parseCallback($payload, $token);

        $this->assertSame(1, $dto->status);
        $this->assertNull($dto->url);
    }

    public function test_parse_callback_throws_on_tampered_signature(): void
    {
        $payload = ['status' => 2, 'key' => 'uuid-key', 'url' => 'https://x'];
        $token   = $this->signPayload($payload) . 'tampered';

        $this->expectException(InvalidArgumentException::class);

        $this->client->parseCallback($payload, $token);
    }

    public function test_parse_callback_throws_on_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client->parseCallback([], 'not-a-jwt');
    }

    public function test_parse_callback_throws_when_signed_with_wrong_secret(): void
    {
        $payload = ['status' => 2, 'key' => 'uuid-key', 'url' => 'https://x'];
        $token   = $this->signWith($payload, 'wrong-secret');

        $this->expectException(InvalidArgumentException::class);

        $this->client->parseCallback($payload, $token);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function makeModels(): array
    {
        $document = $this->createMock(QaDocument::class);
        $document->method('__get')->willReturnMap([
            ['title', 'Test Document'],
            ['id', 1],
        ]);

        $version = $this->createMock(DocumentVersion::class);
        $version->method('__get')->willReturnMap([
            ['onlyoffice_key', 'uuid-key'],
            ['file_name', 'plan.docx'],
        ]);

        $person = $this->createMock(Person::class);
        $person->method('__get')->willReturnMap([
            ['id', 42],
            ['name', 'Alice'],
        ]);

        return [$document, $version, $person];
    }

    private function signPayload(array $payload): string
    {
        return $this->signWith($payload, 'test-secret');
    }

    private function signWith(array $payload, string $secret): string
    {
        $b64 = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $h   = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p   = $b64(json_encode($payload));
        $s   = $b64(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
        return "{$h}.{$p}.{$s}";
    }
}
