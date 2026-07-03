<?php

namespace App\Http\Controllers;

use App\Clients\OnlyOfficeClient;
use App\Services\Document\OnlyOfficeEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Unauthenticated callback endpoint called by the OnlyOffice Document Server.
 * Always returns {"error": 0} — required by the OnlyOffice callback protocol.
 *
 * @tags OnlyOffice
 */
class OnlyOfficeCallbackController extends Controller
{
    /**
     * @response {"error": 0}
     */
    public function __invoke(
        string $key,
        Request $request,
        OnlyOfficeClient $client,
        OnlyOfficeEditorService $service,
    ): JsonResponse {
        $payload = $request->all();
        // inBody=false (our config): token is in Authorization header, not body
        $token = $request->bearerToken() ?? ($payload['token'] ?? '');

        try {
            $dto = $client->parseCallback($payload, $token);

            if ($dto->key === $key) {
                $service->handleCallback($key, $payload);
            }
        } catch (\Throwable) {
            // Swallow — OnlyOffice requires {"error": 0} regardless
        }

        return response()->json(['error' => 0]);
    }
}
