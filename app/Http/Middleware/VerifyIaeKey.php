<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIaeKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-IAE-KEY');

        $validKeys = array_filter([
            config('app.api_key'),
            env('IAE_API_KEY'),
            102022400221, // NIM Saya
        ]);

        if (! $apiKey || ! in_array($apiKey, $validKeys)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid or missing API Key',
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}
