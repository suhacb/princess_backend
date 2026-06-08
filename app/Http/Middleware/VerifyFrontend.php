<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use App\Services\User\UserService;
use Closure;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontend
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserService $userService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accessToken  = $request->bearerToken();
        $refreshToken = $request->header('X-Refresh-Token');
        $appName      = $request->header('X-Application-Name');
        $appUrl       = $request->header('X-Client-Url');

        if (! $accessToken || ! $refreshToken || ! $appName || ! $appUrl) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->authService->validate($accessToken, $refreshToken, $appName, $appUrl);

            if (! $response->successful()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } catch (ConnectionException $e) {
            logger()->error('Token validation service unreachable', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Server error'], $e->getCode() ?: 400);
        }

        try {
            $user = $this->userService->handleUserFromToken($accessToken);
            Auth::login($user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        } catch (Exception $e) {
            logger()->error('Handle user token error', ['exception' => $e]);
            return response()->json(['error' => 'Handle user token error'], 500);
        }

        return $next($request);
    }
}
