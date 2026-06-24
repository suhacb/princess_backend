<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuthService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    /**
     * Get auth gateway login URL.
     *
     * Returns a redirect URI to the auth gateway login page. The frontend
     * should redirect the user to this URL to initiate the Keycloak login flow.
     *
     * @unauthenticated
     * @response {"redirect_uri": "http://auth.example.com:9020/login?appName=Princess&appUrl=http://localhost:10100"}
     */
    public function login(): JsonResponse
    {
        return response()->json(['redirect_uri' => $this->service->login()]);
    }

    /**
     * Validate access token against the auth gateway.
     *
     * Forwards the token to the auth backend for validation and returns the result.
     * Requires `Authorization: Bearer <token>`, `X-Refresh-Token`, `X-Application-Name`,
     * and `X-Client-Url` headers.
     *
     * @unauthenticated
     * @response {"valid": true}
     * @response 401 {"error": "Unauthorized"}
     * @response 503 {"error": "Token validation service unavailable"}
     */
    public function validateAccessToken(Request $request): JsonResponse
    {
        if ($request->attributes->get('e2e_authenticated')) {
            return response()->json('true', 200);
        }

        $accessToken  = $request->bearerToken();
        $refreshToken = $request->header('X-Refresh-Token');
        $appName      = $request->header('X-Application-Name');
        $appUrl       = $request->header('X-Client-Url');

        if (! $accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->validate($accessToken, $refreshToken, $appName, $appUrl);

            if ($response->successful()) {
                $body = $response->json();
                return response()->json($body === true ? 'true' : $body, $body === false ? 401 : 200);
            }

            return response()->json('false', 401);
        } catch (RequestException $e) {
            logger()->error('Token validation HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Server error'], $e->getCode() ?: 400);
        }
    }

    /**
     * Log out from the auth gateway.
     *
     * Forwards the logout request to the auth backend and invalidates the session.
     * Requires `Authorization: Bearer <token>`, `X-Refresh-Token`, `X-Application-Name`,
     * and `X-Client-Url` headers.
     *
     * @unauthenticated
     * @response {"message": "Logged out successfully"}
     * @response 401 {"error": "Unauthorized"}
     * @response 503 {"error": "Logout service unavailable"}
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken  = $request->bearerToken();
        $refreshToken = $request->header('X-Refresh-Token');
        $appName      = $request->header('X-Application-Name');
        $appUrl       = $request->header('X-Client-Url');

        if (! $accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->logout($accessToken, $refreshToken, $appName, $appUrl);

            if ($response->successful()) {
                Auth::logout();
                return response()->json(['message' => 'Logged out successfully']);
            }

            return response()->json(['error' => 'Logout failed', 'status' => $response->status()], $response->status());
        } catch (RequestException|ConnectionException $e) {
            logger()->error('Logout HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Logout service unavailable'], 503);
        } catch (Exception $e) {
            logger()->error('Logout error', ['exception' => $e]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
