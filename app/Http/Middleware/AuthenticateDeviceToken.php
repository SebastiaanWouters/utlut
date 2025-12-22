<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check Authorization header first (preferred), then query parameter (for audio caching)
        $token = $request->bearerToken() ?: $request->query('token');

        if (! $token) {
            return response()->json(['ok' => false, 'error' => 'No token provided'], 401);
        }

        $deviceToken = \App\Models\DeviceToken::where('token_hash', hash('sha256', $token))->first();

        if (! $deviceToken) {
            return response()->json(['ok' => false, 'error' => 'Invalid token'], 401);
        }

        // Set the device token on the request for access in the controller
        $request->merge(['device_token' => $deviceToken]);

        return $next($request);
    }
}
