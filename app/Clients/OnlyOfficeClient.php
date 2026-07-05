<?php

namespace App\Clients;

use App\Documents\OnlyOfficeCallbackDto;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\QaDocument;
use InvalidArgumentException;

class OnlyOfficeClient
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly string $serverUrl,
        private readonly ?string $publicUrl = null,
    ) {}

    /**
     * Build a JWT-signed editor config payload for the OnlyOffice JS SDK.
     * $fileUrl is the presigned S3 URL for OnlyOffice to fetch the current file;
     * null means OnlyOffice opens an empty document.
     */
    public function generateEditorConfig(
        QaDocument $document,
        DocumentVersion $version,
        Person $user,
        string $callbackUrl,
        ?string $fileUrl,
        bool $readOnly = false,
    ): array {
        $extension = strtolower(pathinfo($version->file_name, PATHINFO_EXTENSION) ?: 'docx');

        // Historical versions never have an onlyoffice_key (only editing-session
        // placeholder versions do), so read-only view sessions get a synthetic,
        // stable-per-version key instead of relying on that column.
        $key = $readOnly ? "ro-{$version->id}" : $version->onlyoffice_key;

        $config = [
            'document' => [
                'fileType'    => $extension,
                'key'         => $key,
                'title'       => $document->title,
                'url'         => $fileUrl,
                'permissions' => [
                    'edit' => ! $readOnly,
                ],
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'mode'        => $readOnly ? 'view' : 'edit',
                'callbackUrl' => $callbackUrl,
                'user'        => [
                    'id'   => (string) $user->id,
                    'name' => $user->name,
                ],
            ],
        ];

        $config['token']     = $this->sign($config);
        $config['serverUrl'] = $this->publicUrl ?? $this->serverUrl;

        return $config;
    }

    /**
     * Verify the OnlyOffice callback JWT and return a typed DTO.
     * $token is the value of the `token` field in the callback body.
     *
     * @throws InvalidArgumentException when the signature is invalid
     */
    public function parseCallback(string $token): OnlyOfficeCallbackDto
    {
        $verified = $this->verify($token);

        // OnlyOffice wraps the callback body under a 'payload' key in the JWT claims
        // when token.enable.request.inbox is active (Authorization-header mode).
        $claims = $verified['payload'] ?? $verified;

        return OnlyOfficeCallbackDto::from($claims);
    }

    private function sign(array $payload): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims  = $this->base64url(json_encode($payload));
        $sig     = $this->base64url(hash_hmac('sha256', "{$header}.{$claims}", $this->jwtSecret, true));

        return "{$header}.{$claims}.{$sig}";
    }

    private function verify(string $token): array
    {
        if ($this->jwtSecret === '') {
            throw new InvalidArgumentException('OnlyOffice JWT secret is not configured.');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT format.');
        }

        [$header, $claims, $sig] = $parts;

        $headerDecoded = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
        if (($headerDecoded['alg'] ?? null) !== 'HS256') {
            throw new InvalidArgumentException('Unsupported JWT algorithm.');
        }

        $expected = $this->base64url(hash_hmac('sha256', "{$header}.{$claims}", $this->jwtSecret, true));

        if (! hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('Invalid JWT signature.');
        }

        $decoded = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JWT payload.');
        }

        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            throw new InvalidArgumentException('JWT has expired.');
        }

        return $decoded;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
