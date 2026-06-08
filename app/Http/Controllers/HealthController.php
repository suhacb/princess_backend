<?php

namespace App\Http\Controllers;

use App\Contracts\AuthGatewayClientContract;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function authBackend(): JsonResponse
    {
        $healthy = app(AuthGatewayClientContract::class)->ping();

        return response()->json(
            ['status' => $healthy ? 'ok' : 'unavailable'],
            $healthy ? 200 : 503,
        );
    }
}
