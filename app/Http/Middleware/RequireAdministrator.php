<?php

namespace App\Http\Middleware;

use Closure;

class RequireAdministrator
{
    public function handle($request, Closure $next)
    {
        $user = $request->user()?->fresh();
        if (! $user || $user->disabled || $user->deleted_at) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->admin !== 1) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }
        // JWTGuard's setUser is also used by Laravel's actingAs test helper. Real
        // HTTP requests must first pass auth:api, which rejects a missing token.
        if (auth('api')->getToken() !== null) {
            $version = auth('api')->payload()->get('av');
            if (! is_int($version) || $version !== $user->auth_version) {
                return response()->json(['message' => 'Session expired. Please sign in again.'], 401);
            }
        }
        return $next($request);
    }
}
