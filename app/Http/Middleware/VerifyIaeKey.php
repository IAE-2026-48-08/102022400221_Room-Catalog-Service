<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIaeKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-IAE-KEY');

        if (empty($apiKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Header X-IAE-KEY is required.',
                'errors'  => null,
            ], 401);
        }

        $validKey = config('app.iae_api_key', env('IAE_API_KEY'));

        if ($apiKey !== $validKey) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Invalid API Key.',
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}
