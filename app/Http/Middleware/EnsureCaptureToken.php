<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the capture API with the static bearer token from PKM_CAPTURE_TOKEN.
 */
class EnsureCaptureToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('pkm.capture_token');

        if (blank($token) || ! is_string($request->bearerToken()) || ! hash_equals($token, $request->bearerToken())) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid capture token.');
        }

        return $next($request);
    }
}
