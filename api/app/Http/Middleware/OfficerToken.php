<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal officer gate for the demo: a single shared bearer token issued by
 * the hardcoded officer login. Good enough for a weekend demo, and the one
 * place to swap for Sanctum or Cognito later.
 */
class OfficerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('jalrakshak.officer.token');
        $given = $request->bearerToken() ?: $request->header('X-Officer-Token');

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            return response()->json([
                'message' => 'Officer sign in required.',
            ], 401);
        }

        return $next($request);
    }
}
