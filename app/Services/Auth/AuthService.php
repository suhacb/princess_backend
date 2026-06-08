<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AuthService
{
    private string $appName;
    private string $authUrlFrontend;
    private int    $authPortFrontend;
    private string $authUrlBackend;
    private int    $authPortBackend;
    private string $appFrontendUrl;
    private int    $appFrontendPort;

    public function __construct()
    {
        $this->appName          = config('princess.name');
        $this->authUrlFrontend  = config('princess.auth.url_frontend');
        $this->authPortFrontend = (int) config('princess.auth.port_frontend');
        $this->authUrlBackend   = config('princess.auth.url_backend');
        $this->authPortBackend  = (int) config('princess.auth.port_backend');
        $this->appFrontendUrl   = config('princess.frontend.url');
        $this->appFrontendPort  = (int) config('princess.frontend.port');
    }

    public function login(): string
    {
        $query = http_build_query([
            'appName' => $this->appName,
            'appUrl'  => $this->appFrontendUrl . ':' . $this->appFrontendPort,
        ]);

        return "{$this->authUrlFrontend}:{$this->authPortFrontend}/login?{$query}";
    }

    public function validate(string $accessToken, string $refreshToken, string $appName, string $appUrl): Response
    {
        return Http::withToken($accessToken)
            ->withHeaders([
                'X-Refresh-Token'    => $refreshToken,
                'X-Application-Name' => $appName,
                'X-Client-Url'       => $appUrl,
            ])
            ->get("{$this->authUrlBackend}:{$this->authPortBackend}/api/auth/validate-access-token");
    }

    public function logout(string $accessToken, string $refreshToken, string $appName, string $appUrl): Response
    {
        return Http::withToken($accessToken)
            ->withHeaders([
                'X-Refresh-Token'    => $refreshToken,
                'X-Application-Name' => $appName,
                'X-Client-Url'       => $appUrl,
            ])
            ->post("{$this->authUrlBackend}:{$this->authPortBackend}/api/auth/logout");
    }
}
