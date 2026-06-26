<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Build a standardized successful API response.
     */
    protected function successResponse(mixed $data, string $message = 'Request successful', array $meta = [], int $status = 200): JsonResponse
    {
        if (is_object($data) && method_exists($data, 'resolve')) {
            $data = $data->resolve();
        }

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $status);
    }

    /**
     * Build a standardized error API response.
     */
    protected function errorResponse(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
