<?php

namespace App\Http\Controllers;

use App\Contracts\AuthGatewayClientContract;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Application health check.
     *
     * Returns 200 when the application is running.
     *
     * @unauthenticated
     * @response {"status": "ok"}
     */
    public function check(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Auth gateway connectivity check.
     *
     * Pings the auth backend and reports its reachability.
     *
     * @unauthenticated
     * @response {"status": "ok"}
     * @response 503 {"status": "unavailable"}
     */
    public function authBackend(): JsonResponse
    {
        if (app(AuthGatewayClientContract::class)->ping()) {
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'unavailable'], 503);
    }
}
