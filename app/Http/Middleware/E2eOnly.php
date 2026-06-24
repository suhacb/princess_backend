<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class E2eOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->attributes->get('e2e_authenticated')) {
            return response()->json(['error' => 'E2E token required'], 401);
        }

        return $next($request);
    }
}
