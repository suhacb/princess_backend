<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class E2eAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-E2E-Token');
        $expected = config('app.e2e_token');

        if (!$incoming || !$expected) {
            return $next($request);
        }

        if (!hash_equals($expected, $incoming)) {
            return response()->json(['error' => 'Invalid E2E token'], 401);
        }

        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('e2e');

        $user = User::firstOrCreate(
            ['email' => 'e2e@princess.test'],
            ['name' => 'E2E User', 'external_id' => 'e2e-user', 'username' => 'e2e'],
        );
        Auth::login($user);

        $request->attributes->set('e2e_authenticated', true);

        $response = $next($request);

        DB::setDefaultConnection($original);

        return $response;
    }
}
