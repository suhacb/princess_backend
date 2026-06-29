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
    ): array {
        $extension = strtolower(pathinfo($version->file_name, PATHINFO_EXTENSION) ?: 'docx');

        $config = [
            'document' => [
                'fileType' => $extension,
                'key'      => $version->onlyoffice_key,
                'title'    => $document->title,
                'url'      => $fileUrl,
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'user'        => [
                    'id'   => (string) $user->id,
                    'name' => $user->name,
                ],
            ],
        ];

        $config['token'] = $this->sign($config);

        return $config;
    }

    /**
     * Verify the OnlyOffice callback JWT and return a typed DTO.
     * $token is the value of the `token` field in the callback body.
     *
     * @throws InvalidArgumentException when the signature is invalid
     */
    public function parseCallback(array $payload, string $token): OnlyOfficeCallbackDto
    {
        $verified = $this->verify($token);

        return OnlyOfficeCallbackDto::from($verified);
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
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT format.');
        }

        [$header, $claims, $sig] = $parts;

        $expected = $this->base64url(hash_hmac('sha256', "{$header}.{$claims}", $this->jwtSecret, true));

        if (! hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('Invalid JWT signature.');
        }

        $decoded = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JWT payload.');
        }

        return $decoded;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
