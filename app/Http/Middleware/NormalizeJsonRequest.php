<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeJsonRequest
{
    /**
     * Handle an incoming request.
     *
     * Normalizes requests that contain JSON data but have incorrect Content-Type headers.
     * This commonly occurs with iOS Shortcuts which may send JSON with text/plain Content-Type.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the request already has JSON content-type, let it through
        if ($request->isJson()) {
            return $next($request);
        }

        // Try to parse body as JSON for POST/PUT/PATCH requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $content = $request->getContent();

            if (! empty($content)) {
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Replace request data with parsed JSON
                    $request->merge($decoded);
                }
            }
        }

        return $next($request);
    }
}
